<?php

namespace Tests\Feature;

use App\Enums\Gender;
use App\Enums\SantriStatus;
use App\Enums\SantriTrack;
use App\Models\Organization;
use App\Models\SantriProfile;
use App\Models\User;
use App\Support\Role;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class UxPolishTest extends TestCase
{
    use RefreshDatabase;

    private User $ketua;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->travelTo('2026-09-21 09:00:00');

        $this->ketua = User::factory()->create(['username' => 'ketua-ux', 'name' => 'TPQ Al-Ihsan', 'place_name' => 'TPQ Al-Ihsan']);
        $this->ketua->assignRole(Role::Ketua);
        Organization::provision($this->ketua);
        $this->ketua = $this->ketua->fresh();
    }

    public function test_offline_page_is_public_and_the_service_worker_falls_back_to_it(): void
    {
        $this->get(route('offline'))->assertOk()->assertSee('Sedang offline');

        $this->get(route('pwa.service-worker'))
            ->assertOk()
            ->assertSee("const OFFLINE_URL = '/offline';", false)
            ->assertSee('networkOrOfflinePage', false);
    }

    public function test_destructive_actions_use_the_confirm_sheet_instead_of_native_confirm(): void
    {
        $viewsUsingNativeConfirm = collect(File::allFiles(resource_path('views')))
            ->filter(fn ($file) => str_contains($file->getContents(), 'confirm('))
            ->map(fn ($file) => $file->getRelativePathname())
            ->values()
            ->all();

        $this->assertSame([], $viewsUsingNativeConfirm);

        $this->santri("Zaid O'Neil", '7001');

        $this->as($this->ketua)->get(route('ketua.santri.index'))
            ->assertOk()
            ->assertSee('data-confirm-sheet', false)
            ->assertSee('data-turbo-confirm="Hapus permanen Zaid O&#039;Neil (NIS 7001)?', false)
            ->assertSee('data-confirm-tone="danger"', false);
    }

    public function test_setoran_form_highlights_santri_without_setoran_today(): void
    {
        $teacher = User::factory()->create(['name' => 'Ahmad', 'organization_id' => $this->ketua->organization_id]);
        $teacher->assignRole(Role::KetuaPengajar);
        $done = $this->santri('Aisyah', '7001');
        $pending = $this->santri('Bilal', '7002');

        $this->as($teacher)->post(route('ops.setoran.store'), [
            'santri_id' => $done->id,
            'setoran_date' => now()->toDateString(),
            'category' => 'hafalan',
            'subtype' => 'doa',
            'doa_name' => 'Doa sebelum makan',
            'status' => 'lulus',
        ])->assertRedirect(route('ops.setoran.index'));

        $this->as($teacher)->get(route('ops.setoran.create'))
            ->assertOk()
            ->assertViewHas('pendingSantriIds', [$pending->id])
            ->assertSee('data-santri-picker', false)
            ->assertSee('Belum setor hari ini (1)');
    }

    public function test_detail_pages_show_a_back_button_in_the_mobile_header(): void
    {
        $this->as($this->ketua)->get(route('ketua.santri.create'))
            ->assertOk()
            ->assertSee('href="'.route('ketua.santri.index').'" class="ui-tap', false)
            ->assertSee('data-back-link', false);

        $this->as($this->ketua)->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('data-back-link', false);
    }

    public function test_status_messages_show_as_toast_except_silent_profile_statuses(): void
    {
        $this->as($this->ketua)->withSession(['status' => 'Santri disimpan.'])->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-toast', false)
            ->assertSee('Santri disimpan.');

        $this->as($this->ketua)->withSession(['status' => 'profile-updated'])->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('data-toast', false);
    }

    private function as(User $user): static
    {
        $user->forceFill(['session_date' => now()->toDateString()])->save();

        return $this->actingAs($user->fresh());
    }

    private function santri(string $name, string $nis): SantriProfile
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
            'joined_at' => '2026-01-10',
            'spp_obligation_from' => '2026-01-01',
        ]);
    }
}
