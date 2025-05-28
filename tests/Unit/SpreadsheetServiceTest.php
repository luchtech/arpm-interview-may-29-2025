<?php

namespace Tests\Unit\Services;

use App\Jobs\ProcessProductImage;
use App\Models\Product;
use App\Services\SpreadsheetService;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class SpreadsheetServiceTest extends TestCase
{
    protected SpreadsheetService $spreadsheetService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->spreadsheetService = new SpreadsheetService;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** @test */
    public function it_processes_spreadsheet_data_and_creates_products()
    {
        Queue::fake();
        Bus::fake();

        $importer = Mockery::mock('stdClass');
        $importer->shouldReceive('import')
            ->once()
            ->andReturn([
                [
                    'product_code' => 'P001',
                    'quantity' => 10,
                    'name' => 'Product 1',
                ],
                [
                    'product_code' => 'P002',
                    'quantity' => 5,
                    'name' => 'Product 2',
                ],
            ]);

        $this->app->instance('importer', $importer);

        // Mock the Product model's create method
        $product_1 = Mockery::mock(Product::class);
        $product_1->shouldReceive('getAttribute')->with('id')->andReturn(1);
        $product_1->shouldReceive('setAttribute')->andReturnSelf();
        $product_1->shouldReceive('save')->andReturn(true);

        $product_2 = Mockery::mock(Product::class);
        $product_2->shouldReceive('getAttribute')->with('id')->andReturn(2);
        $product_2->shouldReceive('setAttribute')->andReturnSelf();
        $product_2->shouldReceive('save')->andReturn(true);

        Product::shouldReceive('create')
            ->once()
            ->with([
                'product_code' => 'P001',
                'quantity' => 10,
                'name' => 'Product 1',
            ])
            ->andReturn($product_1);

        Product::shouldReceive('create')
            ->once()
            ->with([
                'product_code' => 'P002',
                'quantity' => 5,
                'name' => 'Product 2',
            ])
            ->andReturn($product_2);

        $this->spreadsheetService->processSpreadsheet('path/to/spreadsheet.xlsx');

        Bus::assertDispatched(ProcessProductImage::class, 2);
        Bus::assertDispatched(ProcessProductImage::class, fn ($job) => $job->product->is($product_1));
        Bus::assertDispatched(ProcessProductImage::class, fn ($job) => $job->product->is($product_2));
    }

    /** @test */
    public function it_skips_invalid_rows()
    {
        Queue::fake();
        Bus::fake();

        $importer = Mockery::mock('stdClass');
        $importer->shouldReceive('import')
            ->once()
            ->andReturn([
                [
                    'product_code' => 'P001',
                    'quantity' => 10,
                    'name' => 'Valid Product',
                ],
                [
                    'product_code' => 'P001',
                    'quantity' => 5,
                    'name' => 'Invalid Product (Duplicate)',
                ],
                [
                    'product_code' => 'P003',
                    'quantity' => 0,
                    'name' => 'Invalid Product (Quantity)',
                ],
            ]);

        $this->app->instance('importer', $importer);

        Product::shouldReceive('create')
            ->once()
            ->andReturn(Mockery::mock(Product::class));

        $this->spreadsheetService->processSpreadsheet('path/to/spreadsheet.xlsx');

        Bus::assertDispatched(ProcessProductImage::class, 1);
    }

    /** @test */
    public function it_handles_empty_spreadsheet()
    {
        Queue::fake();
        Bus::fake();

        $importer = Mockery::mock('stdClass');
        $importer->shouldReceive('import')
            ->once()
            ->andReturn([]);

        $this->app->instance('importer', $importer);

        Product::shouldNotReceive('create');

        $this->spreadsheetService->processSpreadsheet('path/to/spreadsheet.xlsx');

        Bus::assertNothingDispatched();
    }
}
