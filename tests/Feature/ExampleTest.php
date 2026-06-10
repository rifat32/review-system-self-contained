<?php

namespace Tests\Feature;

use Tests\TestCase;
use Mockery;
use Illuminate\Support\Facades\Mail;
use App\Mail\DevOtpMail;

class ExampleTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['app.env' => 'testing']);
        $_ENV['DEV_ACCESS_KEY'] = 'review_dev';
        $_ENV['DEV_ACCESS_EMAILS'] = 'rifat@example.com,developer@example.com';
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test that root route redirects to dev-login when unauthenticated.
     */
    public function test_root_redirects_to_dev_login()
    {
        $response = $this->get('/');
        $response->assertStatus(302);
        $response->assertRedirect(route('dev.login'));
    }

    /**
     * Test that root route is accessible with developer authenticated session.
     */
    public function test_root_accessible_with_session()
    {
        $response = $this->withSession(['developer_authenticated' => true])->get('/');
        $response->assertStatus(200);
    }

    /**
     * Test that correct developer password sets session.
     */
    public function test_password_verification_success()
    {
        $response = $this->post('/dev-verify-password', [
            'key' => 'review_dev'
        ]);

        $response->assertRedirect(route('dev.login'));
        $response->assertSessionHas('dev_password_verified', true);
    }

    /**
     * Test that incorrect developer password returns error.
     */
    public function test_password_verification_failure()
    {
        $response = $this->post('/dev-verify-password', [
            'key' => 'wrong_password'
        ]);

        $response->assertStatus(302);
        $response->assertSessionHas('error', 'Invalid developer access password.');
        $response->assertSessionMissing('dev_password_verified');
    }

    /**
     * Test sending OTP to an authorized email after password verification.
     */
    public function test_send_otp_to_authorized_email()
    {
        Mail::fake();

        $response = $this->withSession(['dev_password_verified' => true])
            ->post('/dev-send-otp', [
                'email' => 'rifat@example.com'
            ]);

        $response->assertRedirect(route('dev.login'));
        $response->assertSessionHas('dev_otp_email', 'rifat@example.com');
        $response->assertSessionHas('dev_otp_code');
        $response->assertSessionHas('dev_otp_expires_at');
        $response->assertSessionHas('dev_otp_is_decoy', false);
        
        Mail::assertSent(DevOtpMail::class, function ($mail) {
            return $mail->hasTo('rifat@example.com');
        });
    }

    /**
     * Test sending OTP to an unauthorized email (silent decoy flow).
     */
    public function test_send_otp_to_unauthorized_email_decoy()
    {
        Mail::fake();

        $response = $this->withSession(['dev_password_verified' => true])
            ->post('/dev-send-otp', [
                'email' => 'fake_user@example.com'
            ]);

        // Decoy flow redirects to OTP input with a success message to fool the attacker
        $response->assertRedirect(route('dev.login'));
        $response->assertSessionHas('dev_otp_email', 'fake_user@example.com');
        $response->assertSessionHas('dev_otp_code');
        $response->assertSessionHas('dev_otp_expires_at');
        $response->assertSessionHas('dev_otp_is_decoy', true);
        
        // Decoy flow must NOT send any email
        Mail::assertNothingSent();
    }

    /**
     * Test verifying with a valid OTP.
     */
    public function test_verify_otp_valid()
    {
        $response = $this->withSession([
            'dev_password_verified' => true,
            'dev_otp_email' => 'rifat@example.com',
            'dev_otp_code' => '123456',
            'dev_otp_expires_at' => now()->addMinutes(10)->timestamp,
            'dev_otp_is_decoy' => false,
        ])->post('/dev-verify-otp', [
            'otp' => '123456'
        ]);

        $response->assertRedirect('/');
        $response->assertSessionHas('developer_authenticated', true);
        $response->assertSessionMissing('dev_otp_email');
        $response->assertSessionMissing('dev_otp_code');
        $response->assertSessionMissing('dev_password_verified');
    }

    /**
     * Test verifying decoy OTP fails.
     */
    public function test_verify_decoy_otp_fails()
    {
        $response = $this->withSession([
            'dev_password_verified' => true,
            'dev_otp_email' => 'fake_user@example.com',
            'dev_otp_code' => 'DECOY_ABC123XYZ',
            'dev_otp_expires_at' => now()->addMinutes(10)->timestamp,
            'dev_otp_is_decoy' => true,
        ])->post('/dev-verify-otp', [
            'otp' => '123456'
        ]);

        $response->assertStatus(302);
        $response->assertSessionHas('error', 'Invalid verification code.');
        $response->assertSessionMissing('developer_authenticated');
    }

    /**
     * Test verifying with an invalid OTP.
     */
    public function test_verify_otp_invalid()
    {
        $response = $this->withSession([
            'dev_password_verified' => true,
            'dev_otp_email' => 'rifat@example.com',
            'dev_otp_code' => '123456',
            'dev_otp_expires_at' => now()->addMinutes(10)->timestamp,
            'dev_otp_is_decoy' => false,
        ])->post('/dev-verify-otp', [
            'otp' => '999999'
        ]);

        $response->assertStatus(302);
        $response->assertSessionHas('error', 'Invalid verification code.');
        $response->assertSessionMissing('developer_authenticated');
    }
}
