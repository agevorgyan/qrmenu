<?php

namespace Tests\Unit;

use App\Actions\CreateOrderAction;
use App\Jobs\RecordAnalyticsVisitJob;
use App\Jobs\TranslateMenuJob;
use App\Mail\OrderReceiptMail;
use App\Mail\VendorWelcomeVerificationMail;
use App\Models\Category;
use App\Models\Location;
use App\Models\Product;
use App\Models\Vendor;
use App\Services\AiMenuService;
use App\Services\CustomerSyncService;
use App\Services\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AsyncJobsTest extends TestCase
{
    use RefreshDatabase;

    public function test_record_analytics_visit_job_persists_log(): void
    {
        $vendor = Vendor::create([
            'name' => 'Analytics Cafe',
            'slug' => 'analytics-cafe',
            'email' => 'cafe@analytics.com',
            'password' => bcrypt('password'),
        ]);

        $job = new RecordAnalyticsVisitJob([
            'vendor_id' => $vendor->id,
            'channel' => 'dine_in',
            'user_agent' => 'Mozilla/5.0 Test Agent',
            'ip_address' => '127.0.0.1',
            'visit_date' => now()->format('Y-m-d'),
        ]);

        $job->handle();

        $this->assertDatabaseHas('analytics_logs', [
            'vendor_id' => $vendor->id,
            'channel' => 'dine_in',
            'ip_address' => '127.0.0.1',
        ]);
    }

    public function test_translate_menu_job_translates_categories_and_products(): void
    {
        $vendor = Vendor::create([
            'name' => 'Bistro Translate',
            'slug' => 'bistro-translate',
            'email' => 'bistro@translate.com',
            'password' => bcrypt('password'),
        ]);

        $category = Category::create([
            'vendor_id' => $vendor->id,
            'name' => 'Soups',
            'name_translations' => ['en' => 'Soups', 'hy' => 'Ապուրներ'],
            'sort_order' => 1,
        ]);

        $product = Product::create([
            'vendor_id' => $vendor->id,
            'category_id' => $category->id,
            'name' => 'Mushroom Soup',
            'name_translations' => ['en' => 'Mushroom Soup'],
            'price' => 1800,
            'description' => 'Creamy soup',
        ]);

        $job = new TranslateMenuJob($vendor->id, 'ru');
        $job->handle(app(AiMenuService::class), app(TenantContext::class));

        $category->refresh();
        $product->refresh();

        $this->assertArrayHasKey('ru', $category->name_translations);
        $this->assertArrayHasKey('ru', $product->name_translations);
    }

    public function test_order_creation_queues_receipt_email(): void
    {
        Mail::fake();

        $vendor = Vendor::create([
            'name' => 'Fast Food',
            'slug' => 'fast-food',
            'email' => 'fast@food.com',
            'password' => bcrypt('password'),
        ]);

        $location = Location::create([
            'vendor_id' => $vendor->id,
            'name' => 'Drive Thru',
            'slug' => 'drive-thru',
        ]);

        $category = Category::create(['vendor_id' => $vendor->id, 'name' => 'Burgers', 'sort_order' => 1]);
        $product = Product::create(['vendor_id' => $vendor->id, 'category_id' => $category->id, 'name' => 'Cheeseburger', 'price' => 2000]);

        $action = new CreateOrderAction(new CustomerSyncService);

        $action->execute($vendor, [
            'location_id' => $location->id,
            'type' => 'takeaway',
            'customer_name' => 'Karen',
            'customer_email' => 'karen@example.com',
            'customer_phone' => '093112233',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1],
            ],
        ]);

        Mail::assertQueued(OrderReceiptMail::class, function ($mail) {
            return $mail->hasTo('karen@example.com');
        });
    }

    public function test_vendor_registration_sends_welcome_mail(): void
    {
        $this->seed();
        Mail::fake();

        $response = $this->post('/register', [
            'type' => 'hotel',
            'name' => 'Verona Boutique Hotel',
            'expected_locations_count' => 3,
            'operating_address' => 'Baghramyan Ave 24, Yerevan',
            'legal_name' => '«Վերոնա Հոտել» ՍՊԸ',
            'legal_address' => 'ք․ Երևան, Բաղրամյան 24',
            'tax_id' => '02698741',
            'director_name' => 'Գևորգ Գևորգյան',
            'contact_person_name' => 'Մարիամ Ավագյան',
            'phone' => '+37491998877',
            'email' => 'info@veronahotel.am',
            'subscription_plan' => 'enterprise',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ]);

        $response->assertRedirect('/email/verify');

        Mail::assertSent(VendorWelcomeVerificationMail::class, function ($mail) {
            return $mail->hasTo('info@veronahotel.am');
        });
    }
}
