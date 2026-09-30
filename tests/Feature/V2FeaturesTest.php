<?php

namespace Tests\Feature;

use App\Enums\AbsenceRequestStatus;
use App\Enums\AttendanceStatus;
use App\Enums\Gender;
use App\Enums\SantriStatus;
use App\Enums\SantriTrack;
use App\Models\AbsenceRequest;
use App\Models\Attendance;
use App\Models\AttendanceSession;
use App\Models\Holiday;
use App\Models\Organization;
use App\Models\SantriProfile;
use App\Models\SppPayment;
use App\Models\TeacherSchedule;
use App\Models\User;
use App\Services\TeacherRoster;
use App\Support\OrganizationContext;
use App\Support\Role;
use App\Support\WhatsApp;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use ZipArchive;

class V2FeaturesTest extends TestCase
{
    use RefreshDatabase;

    private User $ketua;

    private User $pengajar;

    private User $ustaz;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        // Monday
        $this->travelTo('2026-09-21 09:00:00');

        $this->ketua = $this->ketua('ketua-v2', 'TPQ Al-Ihsan');
        $this->ustaz = $this->teacher('Ahmad', Role::KetuaPengajar, '081200000001');
        $this->pengajar = $this->teacher('Budi', Role::Pengajar, '6281200000002');
    }

    public function test_footer_copyright_is_shown_on_guest_and_app_pages(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Copyright KPKL2 - Universitas Pamulang 2026');
        $this->as($this->ketua)->get(route('dashboard'))->assertOk()->assertSee('Copyright KPKL2 - Universitas Pamulang 2026');
    }

    public function test_spp_report_lists_payment_dates_per_month_and_downloads_excel(): void
    {
        $santri = $this->santri('Zaid', '7001', '2026-07-01');
        $this->santri('Aisyah', '7002', '2026-09-01');
        SppPayment::query()->create([
            'santri_id' => $santri->id, 'year' => 2026, 'month' => 7, 'amount' => 25000,
            'paid_at' => '2026-07-05', 'recorded_by' => $this->ketua->id, 'organization_id' => $this->ketua->organization_id,
        ]);

        $query = ['from_month' => 7, 'from_year' => 2026, 'to_month' => 9, 'to_year' => 2026];

        $this->as($this->ketua)->get(route('laporan.spp.index', $query))
            ->assertOk()
            ->assertSeeInOrder(['Nama Santri', 'Jul', 'Agt', 'Sep'])
            ->assertSeeInOrder(['Aisyah', 'Zaid'])
            ->assertSee('05/07/2026')
            ->assertSee('Belum');

        $response = $this->as($this->ketua)->get(route('laporan.spp.download', $query))->assertOk();
        $sheet = $this->xlsxSheet($response, 1);
        $this->assertStringContainsString('Nama Santri', $sheet);
        $this->assertStringContainsString('05/07/2026', $sheet);

        $this->as($this->pengajar)->get(route('laporan.spp.index'))->assertForbidden();
    }

    public function test_attendance_report_marks_holidays_and_has_one_sheet_per_month(): void
    {
        $santri = $this->santri('Zaid', '7101');
        Holiday::query()->create(['date' => '2026-09-17', 'name' => 'Maulid Nabi', 'organization_id' => $this->ketua->organization_id]);
        $session = AttendanceSession::query()->create([
            'session_date' => '2026-09-16', 'opened_by_user_id' => $this->pengajar->id,
            'submitted_at' => now(), 'organization_id' => $this->ketua->organization_id,
        ]);
        Attendance::query()->create(['attendance_session_id' => $session->id, 'santri_id' => $santri->id, 'status' => AttendanceStatus::Sakit]);

        $query = ['from_month' => 8, 'from_year' => 2026, 'to_month' => 9, 'to_year' => 2026];

        $this->as($this->pengajar)->get(route('laporan.absensi.index', $query))
            ->assertOk()
            ->assertSee('Agustus 2026')
            ->assertSee('September 2026')
            ->assertSee('Maulid Nabi', false);

        $response = $this->as($this->pengajar)->get(route('laporan.absensi.download', $query))->assertOk();
        $workbook = $this->xlsxPart($response, 'xl/workbook.xml');
        $this->assertSame(2, substr_count($workbook, '<sheet '));
        $september = $this->xlsxSheet($response, 2);
        $this->assertStringContainsString('Libur', $september);
        $this->assertStringContainsString('Sakit', $september);
    }

    public function test_teacher_schedule_defaults_to_everyone_and_can_limit_a_date(): void
    {
        $this->as($this->ustaz)->post(route('ketua.teacher-schedules.store'), [
            'date_from' => '2026-09-23',
            'date_to' => '2026-09-26',
            'mode' => TeacherSchedule::ModeSelected,
            'teacher_ids' => [$this->pengajar->id],
            'note' => 'Ustaz Ahmad dinas',
        ])->assertRedirect(route('ketua.teacher-schedules.index'));

        // 26 Sep is Saturday and is skipped.
        $this->assertSame(3, TeacherSchedule::query()->count());

        OrganizationContext::set((int) $this->ketua->organization_id);
        $roster = app(TeacherRoster::class);
        $this->assertSame(['Budi'], $roster->forDate(Carbon::parse('2026-09-23'))->pluck('name')->all());
        $this->assertSame(['Ahmad', 'Budi'], $roster->forDate(Carbon::parse('2026-09-22'))->pluck('name')->all());
        OrganizationContext::forget();

        $this->as($this->pengajar)->get(route('ketua.teacher-schedules.index'))->assertForbidden();
    }

    public function test_whatsapp_numbers_are_normalized(): void
    {
        $this->assertSame('6281234567890', WhatsApp::normalize('0812-3456-7890'));
        $this->assertSame('6281234567890', WhatsApp::normalize('+62 812 3456 7890'));
        $this->assertSame('6281234567890', WhatsApp::normalize('https://wa.me/6281234567890'));
        $this->assertSame('https://wa.me/6281234567890', WhatsApp::link('081234567890'));
        $this->assertNull(WhatsApp::link('12345'));

        $this->as($this->ketua)->post(route('ketua.ustaz.store'), [
            'teaching_role' => Role::Pengajar,
            'name' => 'Citra',
            'nip' => 'NIP-900',
            'username' => 'citra',
            'phone' => '0813 1111 2222',
            'is_active' => 1,
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect();

        $this->assertSame('6281311112222', User::query()->where('username', 'citra')->value('phone'));
    }

    public function test_santri_sees_calendar_with_holidays_and_teacher_whatsapp(): void
    {
        $santri = $this->santri('Zaid', '7201');
        Holiday::query()->create(['date' => '2026-09-24', 'name' => 'Libur Yayasan', 'organization_id' => $this->ketua->organization_id]);

        $response = $this->as($santri->user)->get(route('portal.schedule'))
            ->assertOk()
            ->assertSee('September 2026');

        $days = $response->viewData('calendar')->keyBy('date');
        $this->assertTrue($days['2026-09-24']['off']);
        $this->assertSame('Libur Yayasan', $days['2026-09-24']['holiday']);
        $this->assertTrue($days['2026-09-26']['off']);

        $teachers = collect($days['2026-09-23']['teachers'])->keyBy('name');
        $this->assertSame(['Ahmad', 'Budi'], $teachers->keys()->all());
        $this->assertStringStartsWith('https://wa.me/6281200000001?text=', $teachers['Ahmad']['wa']);
        $this->assertSame('0812-0000-0001', $teachers['Ahmad']['phone']);
    }

    public function test_absence_request_cannot_be_backdated(): void
    {
        $santri = $this->santri('Zaid', '7301');

        $this->as($santri->user)->post(route('portal.absence-requests.store'), [
            'date_from' => '2026-09-18',
            'date_to' => '2026-09-18',
            'type' => 'sakit',
            'reason' => 'Demam tinggi',
        ])->assertSessionHasErrors('date_from');

        $this->assertSame(0, AbsenceRequest::query()->count());
    }

    public function test_approved_request_fills_attendance_even_when_the_session_opens_later(): void
    {
        $santri = $this->santri('Zaid', '7401');

        $this->as($santri->user)->post(route('portal.absence-requests.store'), [
            'date_from' => '2026-09-22',
            'date_to' => '2026-09-22',
            'type' => 'sakit',
            'reason' => 'Demam tinggi',
        ])->assertRedirect(route('portal.absence-requests.index'));

        $request = AbsenceRequest::query()->firstOrFail();
        $this->assertSame(AbsenceRequestStatus::Pending, $request->status);
        $this->as($santri->user)->get(route('portal.absence-requests.index'))->assertSee('Menunggu');

        $this->as($this->ketua)->post(route('ops.absence-requests.approve', $request))->assertRedirect();
        $this->assertSame(AbsenceRequestStatus::Approved, $request->fresh()->status);
        $this->as($santri->user)->get(route('portal.absence-requests.index'))->assertSee('Disetujui');

        $this->travelTo('2026-09-22 08:00:00');
        $this->as($this->pengajar)->get(route('ops.attendance.index'))->assertOk();

        $attendance = Attendance::query()->where('santri_id', $santri->id)->firstOrFail();
        $this->assertSame(AttendanceStatus::Sakit, $attendance->status);
        $this->assertStringContainsString('Demam tinggi', (string) $attendance->note);
    }

    public function test_late_approval_updates_existing_attendance_and_teacher_can_still_change_it(): void
    {
        $santri = $this->santri('Zaid', '7501');

        $this->as($santri->user)->post(route('portal.absence-requests.store'), [
            'date_from' => '2026-09-21',
            'date_to' => '2026-09-21',
            'type' => 'izin',
            'reason' => 'Acara keluarga',
        ]);
        $this->as($this->pengajar)->get(route('ops.attendance.index'))->assertOk();
        $session = AttendanceSession::query()->firstOrFail();
        $this->assertSame(AttendanceStatus::Hadir, Attendance::query()->where('santri_id', $santri->id)->first()->status);

        $this->travelTo('2026-09-23 09:00:00');
        $this->as($this->ustaz)->post(route('ops.absence-requests.approve', AbsenceRequest::query()->firstOrFail()));
        $this->assertSame(AttendanceStatus::Izin, Attendance::query()->where('santri_id', $santri->id)->first()->status);

        $this->as($this->pengajar)->put(route('ops.attendance.update', $session), [
            'rows' => [$santri->id => ['status' => 'hadir', 'note' => 'Ternyata datang']],
        ])->assertRedirect();
        $this->assertSame(AttendanceStatus::Hadir, Attendance::query()->where('santri_id', $santri->id)->first()->status);
    }

    public function test_rejected_request_marks_attendance_alfa(): void
    {
        $santri = $this->santri('Zaid', '7601');

        $this->as($santri->user)->post(route('portal.absence-requests.store'), [
            'date_from' => '2026-09-21',
            'date_to' => '2026-09-22',
            'type' => 'izin',
            'reason' => 'Main ke rumah saudara',
        ]);
        $this->as($this->pengajar)->get(route('ops.attendance.index'))->assertOk();

        $this->as($this->ketua)->post(route('ops.absence-requests.reject', AbsenceRequest::query()->firstOrFail()), [
            'review_note' => 'Bukan alasan mendesak',
        ])->assertRedirect();

        $this->assertSame(AttendanceStatus::Alfa, Attendance::query()->where('santri_id', $santri->id)->first()->status);

        $this->travelTo('2026-09-22 08:00:00');
        $this->as($this->pengajar)->get(route('ops.attendance.index'))->assertOk();
        $this->assertSame(2, Attendance::query()->where('santri_id', $santri->id)->where('status', 'alfa')->count());

        $this->as($santri->user)->get(route('portal.absence-requests.index'))
            ->assertSee('Ditolak')
            ->assertSee('Bukan alasan mendesak');
    }

    public function test_ketua_cannot_review_requests_from_another_place(): void
    {
        $santri = $this->santri('Zaid', '7701');
        $this->as($santri->user)->post(route('portal.absence-requests.store'), [
            'date_from' => '2026-09-22',
            'date_to' => '2026-09-22',
            'type' => 'izin',
            'reason' => 'Acara keluarga',
        ]);

        $other = $this->ketua('ketua-lain-v2', 'TPQ Lain');

        $this->as($other)->get(route('ops.absence-requests.index'))->assertOk()->assertDontSee('Acara keluarga');
        $this->as($other)->post(route('ops.absence-requests.approve', AbsenceRequest::query()->firstOrFail()))->assertNotFound();
    }

    public function test_santri_lists_are_scrollable_and_searchable_by_name_or_nis(): void
    {
        $this->santri('Zaid Harun', '7001');

        $routeNames = [
            'laporan.progress.bacaan.index',
            'laporan.progress.hafalan.index',
            'ketua.santri.index',
            'laporan.spp.index',
            'laporan.absensi.index',
        ];

        foreach ($routeNames as $routeName) {
            $this->as($this->ketua)->get(route($routeName))
                ->assertOk()
                ->assertSee('x-data="searchList"', false)
                ->assertSee('data-search="zaid harun 7001"', false)
                ->assertSee('ui-scroll', false);
        }
    }

    public function test_pages_navigate_with_turbo_and_excel_downloads_bypass_it(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('<meta name="turbo-cache-control" content="no-cache">', false);

        $this->as($this->ketua)->get(route('laporan.spp.index'))
            ->assertOk()
            ->assertSee('<meta name="view-transition" content="same-origin">', false)
            ->assertSee('class="btn-primary" data-turbo="false"', false);
    }

    private function as(User $user): static
    {
        $user->forceFill(['session_date' => now()->toDateString()])->save();

        return $this->actingAs($user->fresh());
    }

    private function ketua(string $username, string $place): User
    {
        $user = User::factory()->create(['username' => $username, 'name' => $place, 'place_name' => $place]);
        $user->assignRole(Role::Ketua);
        Organization::provision($user);

        return $user->fresh();
    }

    private function teacher(string $name, string $role, string $phone): User
    {
        $user = User::factory()->create([
            'name' => $name,
            'phone' => WhatsApp::normalize($phone),
            'organization_id' => $this->ketua->organization_id,
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function santri(string $name, string $nis, string $joinedAt = '2026-01-10'): SantriProfile
    {
        $user = User::factory()->create(['name' => $name, 'organization_id' => $this->ketua->organization_id]);
        $user->assignRole(Role::Santri);

        return SantriProfile::query()->create([
            'user_id' => $user->id,
            'nis' => $nis,
            'gender' => Gender::LakiLaki,
            'status' => SantriStatus::Aktif,
            'track' => SantriTrack::Alquran,
            'organization_id' => $this->ketua->organization_id,
            'joined_at' => $joinedAt,
            'spp_obligation_from' => Carbon::parse($joinedAt)->startOfMonth()->toDateString(),
        ])->load('user');
    }

    private function xlsxPart(TestResponse $response, string $part): string
    {
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($response->baseResponse->getFile()->getPathname()) === true);
        $content = (string) $zip->getFromName($part);
        $zip->close();

        return $content;
    }

    private function xlsxSheet(TestResponse $response, int $number): string
    {
        return $this->xlsxPart($response, "xl/worksheets/sheet{$number}.xml");
    }
}
