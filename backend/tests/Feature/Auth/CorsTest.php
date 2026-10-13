<?php

namespace Tests\Feature\Auth;

use PHPUnit\Framework\Attributes\Test;

class CorsTest extends AuthTestCase
{
    #[Test]
    public function une_origine_non_listee_n_est_pas_autorisee(): void
    {
        config(['cors.allowed_origins' => []]);

        $this->withHeaders(['Origin' => 'https://site-malveillant.example', 'Access-Control-Request-Method' => 'POST'])
            ->options('/api/v1/auth/login')
            ->assertHeaderMissing('Access-Control-Allow-Origin');
    }

    #[Test]
    public function une_origine_listee_est_autorisee(): void
    {
        config(['cors.allowed_origins' => ['http://localhost:8090']]);

        $this->withHeaders(['Origin' => 'http://localhost:8090', 'Access-Control-Request-Method' => 'POST'])
            ->options('/api/v1/auth/login')
            ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:8090');
    }
}
