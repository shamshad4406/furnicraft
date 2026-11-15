<?php

namespace Tests\Feature\Front;

use App\Http\Controllers\Front\Auth\CustomerAuthController;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Wishlist;
use App\Services\CartService;
use Database\Seeders\SettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * What a visitor collected before signing in has to come with them.
 *
 * This is driven through the controller rather than over HTTP because the
 * test client starts a new session for every request, and the whole point
 * here is what happens to one session as it is signed in.
 *
 * The trap it guards: Auth::attempt() migrates the session the moment the
 * credentials check out, so anything reading the session id after that call
 * sees the *new* id — while the guest's cart and wishlist rows are still
 * keyed on the old one. Capturing the id too late loses them silently.
 */
class GuestSessionAdoptionTest extends TestCase
{
    use RefreshDatabase;

    protected Customer $customer;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([SettingSeeder::class]);

        $category = Category::create(['name' => 'Sofa', 'slug' => 'sofa', 'is_active' => true]);

        $this->product = Product::create([
            'category_id' => $category->id,
            'name' => 'Blue Sofa', 'slug' => 'blue-sofa', 'sku' => Str::upper(Str::random(8)),
            'price' => 10000, 'stock_quantity' => 10, 'gst_rate' => 18, 'status' => 'active',
        ]);

        $this->customer = Customer::create([
            'name' => 'Ravi Kumar', 'email' => 'ravi@example.com', 'phone' => '9811122233',
            'password' => 'Secret12345', 'is_active' => true,
        ]);
    }

    /**
     * A request on a live session, the way the real sign-in form arrives.
     *
     * @param  array<string, mixed>  $input
     */
    protected function sessionRequest(array $input = [], string $uri = '/'): Request
    {
        $request = Request::create($uri, $input === [] ? 'GET' : 'POST', $input);
        $request->setLaravelSession(app('session.store'));
        app('session.store')->start();

        return $request;
    }

    public function test_signing_in_carries_the_guest_cart_and_wishlist_across(): void
    {
        $request = $this->sessionRequest();
        $guestSessionId = $request->session()->getId();

        app(CartService::class)->add($request, $this->product, null, 2);

        Wishlist::create([
            'session_id' => $guestSessionId,
            'product_id' => $this->product->id,
        ]);

        $this->assertNull(Cart::first()->customer_id);

        $login = $this->sessionRequest([
            'email' => 'ravi@example.com',
            'password' => 'Secret12345',
        ], '/login');

        $this->assertSame($guestSessionId, $login->session()->getId(),
            'The sign-in request must run on the same session the guest was using.');

        app(CustomerAuthController::class)->login($login);

        // attempt() migrated the session, so the id has moved on...
        $this->assertNotSame($guestSessionId, $login->session()->getId());

        // ...but the cart and the wishlist came with the customer anyway.
        $this->assertSame($this->customer->id, Cart::first()->customer_id);
        $this->assertSame(2, CartItem::first()->quantity);

        $saved = Wishlist::first();
        $this->assertSame($this->customer->id, $saved->customer_id);
        $this->assertNull($saved->session_id);
    }

    public function test_registering_carries_the_guest_cart_across(): void
    {
        $request = $this->sessionRequest();
        app(CartService::class)->add($request, $this->product, null, 3);

        $register = $this->sessionRequest([
            'name' => 'New Buyer',
            'email' => 'new@example.com',
            'phone' => '9800000123',
            'password' => 'Secret12345',
            'password_confirmation' => 'Secret12345',
        ], '/register');

        app(CustomerAuthController::class)->register($register);

        $created = Customer::where('email', 'new@example.com')->firstOrFail();

        $this->assertSame($created->id, Cart::first()->customer_id);
        $this->assertSame(3, CartItem::first()->quantity);
    }

    public function test_a_failed_sign_in_leaves_the_guest_cart_where_it_was(): void
    {
        $request = $this->sessionRequest();
        $guestSessionId = $request->session()->getId();

        app(CartService::class)->add($request, $this->product, null, 1);

        $login = $this->sessionRequest([
            'email' => 'ravi@example.com',
            'password' => 'WrongPassword1',
        ], '/login');

        try {
            app(CustomerAuthController::class)->login($login);
            $this->fail('A wrong password should not sign anyone in.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            // expected
        }

        $cart = Cart::first();
        $this->assertNull($cart->customer_id);
        $this->assertSame($guestSessionId, $cart->session_id);
    }
}
