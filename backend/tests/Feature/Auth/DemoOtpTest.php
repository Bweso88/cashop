<?php

namespace Tests\Feature\Auth;

use App\Domain\Identity\Services\OtpService;
use PHPUnit\Framework\Attributes\Test;

class DemoOtpTest extends AuthTestCase
{
    #[Test]
    public function le_code_n_est_jamais_renvoye_par_defaut(): void
    {
        $this->login('client@cashop.test')->assertStatus(202)->assertJsonMissingPath('demo_code');
    }

    #[Test]
    public function en_demonstration_sans_sms_reel_le_code_est_affiche(): void
    {
        config(['cashop.otp.demo_display' => true, 'services.sms.provider' => 'log']);

        $code = $this->login('client@cashop.test')->assertStatus(202)->json('demo_code');

        $this->assertSame($this->sms->lastCode(), $code);
    }

    #[Test]
    public function jamais_avec_un_fournisseur_sms_reel(): void
    {
        config(['cashop.otp.demo_display' => true, 'services.sms.provider' => 'twilio']);

        $this->login('client@cashop.test')->assertStatus(202)->assertJsonMissingPath('demo_code');
    }

    #[Test]
    public function jamais_en_production(): void
    {
        config(['cashop.otp.demo_display' => true, 'services.sms.provider' => 'log']);
        $this->app['env'] = 'production';

        $this->assertFalse(OtpService::demoDisplay());
    }
}
