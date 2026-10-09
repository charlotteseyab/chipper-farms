<?php

namespace Tests\Concurrency;

class EarTagConcurrencyTest extends ConcurrencyTestCase
{
    public function test_simultaneous_automatic_assignments_have_distinct_tags(): void
    {
        $migration = $this->artisan('migrate', '--force');
        $this->assertSame(0, $migration['exit'], $migration['out'] ?? json_encode($migration));
        $slug = 'tag-race-'.bin2hex(random_bytes(5));
        $setup = $this->runConcurrently(1, <<<PHP
\$org = App\Models\Organization::create(['name' => 'Concurrency tag farm', 'slug' => '{$slug}']);
for (\$i = 0; \$i < 4; \$i++) {
    App\Models\PigManagement::create(['organization_id' => \$org->id, 'ear_tag_number' => '', 'code' => 'RACE-'.\$i, 'gender' => 'F', 'stock_type' => 'standard', 'date_of_birth' => '2026-01-01']);
}
echo \$org->id;
PHP);
        $this->assertSame(0, $setup[0]['exit'], json_encode($setup));
        $orgId = (int) trim($setup[0]['out']);
        $this->assertGreaterThan(0, $orgId);
        $results = $this->runConcurrently(4, <<<PHP
\$org = App\Models\Organization::findOrFail({$orgId});
app(App\Support\CurrentOrganization::class)->bind(\$org);
\$pig = App\Models\PigManagement::where('code', 'RACE-'.\$workerId)->firstOrFail();
\$color = app(App\Services\Stock\Tags\EarTagShadowService::class)->legacyColor(\$org->id);
\$number = app(App\Services\Stock\Tags\EarTagAssignmentService::class)->assignAutomatically(\$pig, \$color, null)->tag->tag_number;
echo \$number;
PHP, isolation: 'READ COMMITTED');
        $numbers = [];
        foreach ($results as $result) {
            $this->assertSame(0, $result['exit'], json_encode($result));
            $numbers[] = trim($result['out']);
        }
        $this->assertCount(4, array_unique($numbers));
        sort($numbers);
        $this->assertSame(['1', '2', '3', '4'], $numbers);
    }
    public function test_simultaneous_requests_for_one_tag_have_exactly_one_winner(): void
    {
        $migration = $this->artisan('migrate', '--force');
        $this->assertSame(0, $migration['exit'], json_encode($migration));
        $slug = 'manual-tag-race-'.bin2hex(random_bytes(5));
        $setup = $this->runConcurrently(1, <<<PHP
\$org = App\Models\Organization::create(['name' => 'Manual tag race', 'slug' => '{$slug}']);
for (\$i = 0; \$i < 4; \$i++) {
    App\Models\PigManagement::create(['organization_id' => \$org->id, 'ear_tag_number' => '', 'code' => 'MANUAL-'.\$i, 'gender' => 'F', 'stock_type' => 'standard', 'date_of_birth' => '2026-01-01']);
}
echo \$org->id;
PHP);
        $this->assertSame(0, $setup[0]['exit'], json_encode($setup));
        $orgId = (int) trim($setup[0]['out']);
        $results = $this->runConcurrently(4, <<<PHP
\$org = App\Models\Organization::findOrFail({$orgId});
app(App\Support\CurrentOrganization::class)->bind(\$org);
\$pig = App\Models\PigManagement::where('code', 'MANUAL-'.\$workerId)->firstOrFail();
\$color = app(App\Services\Stock\Tags\EarTagShadowService::class)->legacyColor(\$org->id);
try {
    app(App\Services\Stock\Tags\EarTagAssignmentService::class)->assign(\$pig, \$color, '5', null);
    echo 'assigned';
} catch (App\Services\Stock\StockValidationException \$e) {
    echo 'unavailable';
}
PHP, isolation: 'READ COMMITTED');
        $outcomes = [];
        foreach ($results as $result) {
            $this->assertSame(0, $result['exit'], json_encode($result));
            $outcomes[] = trim($result['out']);
        }
        $this->assertSame(1, count(array_filter($outcomes, fn ($value) => $value === 'assigned')));
        $this->assertSame(3, count(array_filter($outcomes, fn ($value) => $value === 'unavailable')));
        $verify = $this->runConcurrently(1, <<<PHP
echo App\Models\EarTagAssignment::withoutGlobalScopes()->where('organization_id', {$orgId})->whereNull('released_at')->count();
PHP);
        $this->assertSame('1', trim($verify[0]['out']));
    }

}
