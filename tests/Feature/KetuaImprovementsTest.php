<?php

namespace Tests\Feature;

use App\Enums\Gender;
use App\Enums\SantriStatus;
use App\Enums\SantriTrack;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\SantriProfile;
use App\Models\User;
use App\Services\SppService;
use App\Support\AppSettings;
use App\Support\OrganizationContext;
use App\Support\Role;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KetuaImprovementsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->travelTo('2026-09-25 09:00:00');
    }

    public function test_manifest_uses_al_ihsan_as_the_installed_name(): void
    {
        $this->get(route('pwa.manifest'))
            ->assertOk()
            ->assertJsonPath('short_name', 'Al-Ihsan')
            ->assertJsonPath('name', 'Al-Ihsan');
    }

    public function test_photo_input_accepts_gallery_as_well_as_camera(): void
    {
        $this->ketua('ketua-foto', 'TPQ Foto');

        $this->get(route('daftar.create'))
            ->assertOk()
            ->assertDontSee('capture=', false);
    }

    public function test_session_ends_when_the_date_changes(): void
    {
        $ketua = $this->ketua('ketua-sesi', 'TPQ Sesi');
        $ketua->forceFill(['session_date' => '2026-09-24'])->save();

        $this->actingAs($ketua->fresh())
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));

        $this->post(route('login'), [
            'login' => 'ketua-sesi',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->assertSame('2026-09-25', $ketua->fresh()->session_date?->toDateString());

        $this->travelTo('2026-09-26 00:10:00');

        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_santri_lists_are_alphabetical_and_spp_starts_at_join_month(): void
    {
        $ketua = $this->ketua('ketua-urut', 'TPQ Urut');
        $this->santri($ketua, 'Zainab', '9002');
        $this->santri($ketua, 'Ahmad', '9001', '2026-08-10', '2026-08-01');

        $this->actingAs($ketua)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Hari ini')
            ->assertSee('Taman Pendidikan Al-Qur');

        $this->actingAs($ketua)
            ->get(route('ketua.santri.index'))
            ->assertOk()
            ->assertSeeInOrder(['Ahmad', 'Zainab']);

        $ahmad = SantriProfile::query()->where('nis', '9001')->firstOrFail();
        $keys = collect(app(SppService::class)->historyFor($ahmad))
            ->map(fn (array $row): string => sprintf('%04d-%02d', $row['year'], $row['month']))
            ->all();

        $this->assertSame(['2026-09', '2026-08'], $keys);
    }

    public function test_ketua_cannot_see_another_ketua_data_and_spp_amount_stays_separate(): void
    {
        $first = $this->ketua('ketua-a', 'TPQ Al-Hikmah');
        $second = $this->ketua('ketua-b', 'TPQ An-Nur');
        $santri = $this->santri($first, 'Santri Rahasia', '9101');

        $this->actingAs($second)
            ->get(route('ketua.santri.index'))
            ->assertOk()
            ->assertDontSee('Santri Rahasia');

        $this->actingAs($second)
            ->get(route('ketua.santri.edit', $santri))
            ->assertNotFound();

        OrganizationContext::set((int) $first->organization_id);
        AppSettings::setSppMonthlyAmount(88000);
        OrganizationContext::forget();

        OrganizationContext::set((int) $second->organization_id);
        $this->assertSame(AppSettings::DefaultSppMonthlyAmount, AppSettings::sppMonthlyAmount());
        OrganizationContext::forget();
    }

    public function test_spp_payment_writes_an_audit_remark_visible_only_to_that_ketua(): void
    {
        $ketua = $this->ketua('ketua-audit', 'TPQ Audit');
        $other = $this->ketua('ketua-lain', 'TPQ Lain');
        $teacher = User::factory()->create([
            'name' => 'Budi',
            'organization_id' => $ketua->organization_id,
            'session_date' => '2026-09-25',
        ]);
        $teacher->assignRole(Role::KetuaPengajar);
        $santri = $this->santri($ketua, 'Ahmad', '9201', '2026-09-01', '2026-09-01');

        $this->actingAs($teacher)->post(route('ops.spp.store'), [
            'santri_id' => $santri->id,
            'from_year' => 2026,
            'from_month' => 9,
            'to_year' => 2026,
            'to_month' => 9,
            'paid_at' => '2026-09-25',
        ])->assertRedirect();

        $log = AuditLog::query()->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('Ketua Pengajar Budi', (string) $log->remark);
        $this->assertStringContainsString('menginput pembayaran SPP santri Ahmad', (string) $log->remark);
        $this->assertStringContainsString('Rp 25.000', (string) $log->remark);
        $this->assertSame('25/09/2026 09:00', $log->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i'));

        $this->actingAs($ketua)->get(route('ketua.audit.index'))->assertOk()->assertSee('Ahmad');
        $this->actingAs($other)->get(route('ketua.audit.index'))->assertOk()->assertDontSee('Ahmad');
    }

    private function ketua(string $username, string $place): User
    {
        $user = User::factory()->create([
            'username' => $username,
            'name' => $place,
            'place_name' => $place,
            'session_date' => '2026-09-25',
        ]);
        $user->assignRole(Role::Ketua);
        Organization::provision($user);

        return $user->fresh();
    }

    private function santri(
        User $ketua,
        string $name,
        string $nis,
        string $joinedAt = '2026-01-10',
        string $obligationFrom = '2026-01-01',
    ): SantriProfile {
        $user = User::factory()->create([
            'name' => $name,
            'organization_id' => $ketua->organization_id,
            'session_date' => '2026-09-25',
        ]);
        $user->assignRole(Role::Santri);

        return SantriProfile::query()->create([
            'user_id' => $user->id,
            'nis' => $nis,
            'gender' => Gender::LakiLaki,
            'status' => SantriStatus::Aktif,
            'track' => SantriTrack::Alquran,
            'organization_id' => $ketua->organization_id,
            'joined_at' => $joinedAt,
            'spp_obligation_from' => $obligationFrom,
        ]);
    }
}
