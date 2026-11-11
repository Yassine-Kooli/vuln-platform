<?php

namespace Tests\Feature;

use App\Jobs\CaptureSnapshot;
use App\Jobs\RunScan;
use App\Models\Finding;
use App\Models\Scan;
use App\Models\Target;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class PlatformTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_loads_with_no_targets(): void
    {
        $this->get('/')->assertOk()->assertSee('Aucune cible');
    }

    public function test_adding_a_target_queues_a_snapshot(): void
    {
        Queue::fake();
        $dir = sys_get_temp_dir();

        $this->post('/targets', [
            'name' => 'juice-shop', 'label' => 'Juice Shop', 'url' => 'http://localhost:3000',
            'source_path' => $dir, 'image' => 'bkimminich/juice-shop:latest',
        ])->assertRedirect();

        $this->assertDatabaseHas('targets', ['name' => 'juice-shop', 'url' => 'http://localhost:3000']);
        Queue::assertPushed(CaptureSnapshot::class);
    }

    public function test_target_name_and_url_are_validated(): void
    {
        $this->post('/targets', ['name' => 'Bad Name!', 'url' => 'ftp://nope'])
            ->assertSessionHasErrors(['name', 'url']);
        $this->assertDatabaseCount('targets', 0);
    }

    public function test_missing_source_folder_is_rejected(): void
    {
        $this->post('/targets', ['name' => 'app', 'url' => 'http://localhost:1', 'source_path' => '/no/such/dir'])
            ->assertSessionHasErrors('source_path');
    }

    public function test_starting_a_scan_queues_it(): void
    {
        Queue::fake();
        $target = Target::create(['name' => 'app', 'url' => 'http://localhost:1']);

        $this->post("/targets/{$target->id}/scans", ['stages' => ['sast', 'dast']])->assertRedirect();

        $scan = Scan::first();
        $this->assertSame(['sast', 'dast'], $scan->stages);
        $this->assertSame('queued', $scan->status);
        Queue::assertPushed(RunScan::class);
    }

    public function test_a_scan_needs_at_least_one_stage(): void
    {
        $target = Target::create(['name' => 'app', 'url' => 'http://localhost:1']);
        $this->post("/targets/{$target->id}/scans", [])->assertSessionHasErrors('stages');
    }

    public function test_scan_page_lists_findings_and_filters_them(): void
    {
        $target = Target::create(['name' => 'app', 'url' => 'http://localhost:1']);
        $scan = Scan::create(['target_id' => $target->id, 'stages' => ['sast'], 'status' => 'done']);
        Finding::insert([
            ['scan_id' => $scan->id, 'fingerprint' => 'a', 'tool' => 'semgrep', 'approach' => 'SAST', 'rule' => 'r1',
             'title' => 'Injection SQL', 'severity' => 'high', 'location' => 'routes/login.ts', 'cwe' => 'CWE-89',
             'created_at' => now(), 'updated_at' => now()],
            ['scan_id' => $scan->id, 'fingerprint' => 'b', 'tool' => 'semgrep', 'approach' => 'SAST', 'rule' => 'r2',
             'title' => 'Eval', 'severity' => 'low', 'location' => 'lib/x.ts', 'cwe' => 'CWE-95',
             'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->get("/scans/{$scan->id}")->assertOk()->assertSee('Injection SQL')->assertSee('Eval');
        $this->get("/scans/{$scan->id}?severity=high")->assertSee('Injection SQL')->assertDontSee('Eval');
        $this->get("/scans/{$scan->id}?q=login")->assertSee('routes/login.ts')->assertDontSee('lib/x.ts');
    }

    public function test_report_files_are_only_served_from_the_whitelist(): void
    {
        $target = Target::create(['name' => 'app', 'url' => 'http://localhost:1']);
        $scan = Scan::create(['target_id' => $target->id, 'stages' => ['sast'], 'status' => 'done', 'run_dir' => sys_get_temp_dir()]);

        $this->get("/scans/{$scan->id}/files/../../.env")->assertNotFound();
        $this->get("/scans/{$scan->id}/files/summary.json")->assertNotFound();
    }
}
