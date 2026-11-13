<?php

namespace Tests\Feature\Admin;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ProductImportTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, SettingSeeder::class]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('Admin');

        Category::create(['name' => 'Sofa', 'slug' => 'sofa']);
        Category::create(['name' => 'Table', 'slug' => 'table']);
        Brand::create(['name' => 'UrbanNest', 'slug' => 'urbannest']);
    }

    /**
     * Build a CSV upload from a header and rows.
     *
     * @param  string[]  $header
     * @param  array<int, array<int, string|int|float|null>>  $rows
     */
    protected function csv(array $header, array $rows): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'import').'.csv';
        $handle = fopen($path, 'w');

        fputcsv($handle, $header);

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        fclose($handle);

        return new UploadedFile($path, 'products.csv', 'text/csv', null, true);
    }

    /* ------------------------------- exit criterion: 100 rows succeed */

    public function test_importing_one_hundred_products_succeeds(): void
    {
        $rows = [];

        for ($i = 1; $i <= 100; $i++) {
            $rows[] = ['Imported Product '.$i, 'IMP-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT), 'Sofa', 1000 + $i];
        }

        $response = $this->actingAs($this->admin, 'admin')
            ->post(route('admin.products.import.store'), [
                'file' => $this->csv(['name', 'sku', 'category', 'price'], $rows),
            ]);

        $response->assertRedirect(route('admin.products.import.index'));

        $report = session('import_report');

        $this->assertSame(100, $report['imported']);
        $this->assertSame(0, $report['failed']);
        $this->assertSame([], $report['errors']);
        $this->assertSame(100, Product::count());
    }

    public function test_the_report_lists_each_bad_row_with_its_line_number(): void
    {
        $file = $this->csv(['name', 'sku', 'category', 'price'], [
            ['Good Sofa', 'OK-1', 'Sofa', 9999],            // line 2 — fine
            ['', 'BAD-1', 'Sofa', 1000],                     // line 3 — no name
            ['No Category', 'BAD-2', 'Nowhere', 1000],       // line 4 — unknown category
            ['Bad Price', 'BAD-3', 'Sofa', 'abc'],           // line 5 — price not numeric
            ['Another Good', 'OK-2', 'Table', 2500],         // line 6 — fine
        ]);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.products.import.store'), ['file' => $file]);

        $report = session('import_report');

        // Valid rows still import even though others failed.
        $this->assertSame(2, $report['imported']);
        $this->assertSame(3, $report['failed']);
        $this->assertSame(5, $report['total']);
        $this->assertSame([3, 4, 5], array_column($report['errors'], 'line'));

        $this->assertDatabaseHas('products', ['sku' => 'OK-1']);
        $this->assertDatabaseHas('products', ['sku' => 'OK-2']);
        $this->assertDatabaseMissing('products', ['sku' => 'BAD-1']);

        $messages = implode(' ', $report['errors'][1]['messages']);
        $this->assertStringContainsString('Nowhere', $messages);
    }

    public function test_a_duplicate_sku_inside_the_file_is_reported(): void
    {
        $file = $this->csv(['name', 'sku', 'category', 'price'], [
            ['First', 'SAME-SKU', 'Sofa', 1000],
            ['Second', 'SAME-SKU', 'Sofa', 2000],
        ]);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.products.import.store'), ['file' => $file]);

        $report = session('import_report');

        $this->assertSame(1, $report['imported']);
        $this->assertSame(1, $report['failed']);
        $this->assertStringContainsString('more than once', $report['errors'][0]['messages'][0]);
    }

    public function test_existing_products_are_updated_when_the_option_is_ticked(): void
    {
        $this->actingAs($this->admin, 'admin')->post(route('admin.products.import.store'), [
            'file' => $this->csv(['name', 'sku', 'category', 'price'], [['Original', 'UPD-1', 'Sofa', 1000]]),
        ]);

        $this->actingAs($this->admin, 'admin')->post(route('admin.products.import.store'), [
            'file' => $this->csv(['name', 'sku', 'category', 'price'], [['Renamed', 'UPD-1', 'Sofa', 1500]]),
            'update_existing' => 1,
        ]);

        $report = session('import_report');

        $this->assertSame(1, $report['updated']);
        $this->assertSame(0, $report['imported']);
        $this->assertSame(1, Product::count());
        $this->assertSame('Renamed', Product::first()->name);
        $this->assertSame('1500.00', Product::first()->price);
    }

    public function test_an_existing_sku_is_rejected_when_updates_are_not_allowed(): void
    {
        $this->actingAs($this->admin, 'admin')->post(route('admin.products.import.store'), [
            'file' => $this->csv(['name', 'sku', 'category', 'price'], [['Original', 'DUP-1', 'Sofa', 1000]]),
        ]);

        $this->actingAs($this->admin, 'admin')->post(route('admin.products.import.store'), [
            'file' => $this->csv(['name', 'sku', 'category', 'price'], [['Changed', 'DUP-1', 'Sofa', 2000]]),
            // update_existing omitted
        ]);

        $report = session('import_report');

        $this->assertSame(1, $report['failed']);
        $this->assertSame('Original', Product::first()->name);
    }

    public function test_a_missing_required_column_stops_the_import(): void
    {
        $file = $this->csv(['name', 'category', 'price'], [['No SKU column', 'Sofa', 1000]]);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.products.import.store'), ['file' => $file]);

        $report = session('import_report');

        $this->assertSame(0, $report['imported']);
        $this->assertStringContainsString('sku', $report['errors'][0]['messages'][0]);
        $this->assertSame(0, Product::count());
    }

    public function test_optional_columns_and_yes_no_flags_are_honoured(): void
    {
        $file = $this->csv(
            ['name', 'sku', 'category', 'price', 'brand', 'material', 'sale_price', 'status', 'is_featured', 'stock_quantity'],
            [['Full Row Sofa', 'FULL-1', 'Sofa', 20000, 'UrbanNest', 'Sheesham Wood', 17000, 'active', 'yes', 7]]
        );

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.products.import.store'), ['file' => $file]);

        $product = Product::first();

        $this->assertSame('Sheesham Wood', $product->material);
        $this->assertSame('17000.00', $product->sale_price);
        $this->assertSame('active', $product->status);
        $this->assertTrue($product->is_featured);
        $this->assertSame(7, $product->stock_quantity);
        $this->assertSame('UrbanNest', $product->brand->name);
    }

    public function test_column_order_and_header_case_do_not_matter(): void
    {
        $file = $this->csv(['SKU', 'Price', 'Category', 'Name'], [['REV-1', 4500, 'Table', 'Reversed Columns']]);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.products.import.store'), ['file' => $file]);

        $this->assertDatabaseHas('products', ['sku' => 'REV-1', 'name' => 'Reversed Columns']);
    }

    /* --------------------------------------------------------- export */

    public function test_the_catalog_can_be_exported_as_csv(): void
    {
        Product::create([
            'category_id' => Category::first()->id,
            'name' => 'Exported Sofa', 'slug' => 'exported-sofa', 'sku' => 'EXP-1', 'price' => 5000,
        ]);

        $response = $this->actingAs($this->admin, 'admin')->get(route('admin.products.export'));

        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $body = $response->streamedContent();

        $this->assertStringContainsString('Exported Sofa', $body);
        $this->assertStringContainsString('EXP-1', $body);
    }

    public function test_the_template_can_be_downloaded(): void
    {
        $response = $this->actingAs($this->admin, 'admin')->get(route('admin.products.import.template'));

        $response->assertOk();
        $this->assertStringContainsString('name,sku,category,price', $response->streamedContent());
    }

    public function test_a_non_csv_upload_is_rejected(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.products.import.store'), [
                'file' => UploadedFile::fake()->image('not-a-sheet.jpg'),
            ])
            ->assertSessionHasErrors('file');
    }

    public function test_sales_role_cannot_import_products(): void
    {
        $sales = User::factory()->create();
        $sales->assignRole('Sales');

        $this->actingAs($sales, 'admin')
            ->post(route('admin.products.import.store'), [
                'file' => $this->csv(['name', 'sku', 'category', 'price'], [['X', 'X-1', 'Sofa', 1]]),
            ])
            ->assertForbidden();
    }
}
