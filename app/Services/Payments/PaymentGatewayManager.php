<?php

namespace App\Services\Payments;

use App\Services\Payments\Contracts\PaymentGatewayInterface;
use App\Services\Payments\Gateways\ArcaGateway;
use App\Services\Payments\Gateways\FastShiftGateway;
use App\Services\Payments\Gateways\IdramGateway;
use App\Services\Payments\Gateways\OfflineGateway;
use App\Services\Payments\Gateways\StripeGateway;
use App\Services\Payments\Gateways\TelcellGateway;
use InvalidArgumentException;

class PaymentGatewayManager
{
    /**
     * @var array<string, PaymentGatewayInterface>
     */
    protected array $gateways = [];

    public function __construct()
    {
        $this->registerDefaultGateways();
    }

    protected function registerDefaultGateways(): void
    {
        $this->register(new StripeGateway);
        $this->register(new IdramGateway);
        $this->register(new TelcellGateway);
        $this->register(new FastShiftGateway);
        $this->register(new ArcaGateway);
        $this->register(new OfflineGateway('cash', 'Cash on site'));
        $this->register(new OfflineGateway('pos_terminal', 'POS Terminal on site'));
        $this->register(new OfflineGateway('bank_transfer', 'Bank Transfer'));
    }

    public function register(PaymentGatewayInterface $gateway): void
    {
        $this->gateways[$gateway->getId()] = $gateway;
    }

    public function gateway(string $id): PaymentGatewayInterface
    {
        $id = strtolower($id);
        if ($id === 'ameriabank' || $id === 'vpos') {
            $id = 'arca';
        }

        if (! isset($this->gateways[$id])) {
            throw new InvalidArgumentException("Unsupported payment gateway: {$id}");
        }

        return $this->gateways[$id];
    }

    public function hasGateway(string $id): bool
    {
        $id = strtolower($id);
        if ($id === 'ameriabank' || $id === 'vpos') {
            $id = 'arca';
        }

        return isset($this->gateways[$id]);
    }

    /**
     * @return array<string, PaymentGatewayInterface>
     */
    public function all(): array
    {
        return $this->gateways;
    }
}
