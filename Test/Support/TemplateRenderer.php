<?php
declare(strict_types=1);

namespace Automotive\Garage\Test\Support;

final class TemplateRenderer
{
    /** Test doubles supply block values; the real production template is evaluated. */
    public static function render(array $vehicles, array $values = [], bool $editing = false, bool $catalogue = false,
        ?Settings $settings = null): string
    {
        $settings ??= new Settings();
        $values += ['vehicle_market' => $settings->defaultMarket(), 'registration_country' => $settings->defaultCountry()];
        $block = new class ($vehicles, $values, $editing, $catalogue, $settings) {
            public function __construct(private array $vehicles, private array $values, private bool $editing,
                private bool $catalogue, private Settings $settings)
            {
            }

            public function getVehicles(): array
            {
                return $this->vehicles;
            }

            public function getFormValues(): array
            {
                return $this->values;
            }

            public function isEditing(): bool
            {
                return $this->editing;
            }

            public function hasVehicleCatalogue(): bool
            {
                return $this->catalogue;
            }

            public function plateRequirement(): string { return $this->settings->plateRequirement(); }
            public function isPlateRequired(string $country): bool { return $this->settings->isPlateRequired($country); }
            public function registrationClass(\Automotive\Garage\Model\Vehicle $vehicle): string
            {
                return 'garage-registration' . ($vehicle->registrationCountry === 'US' ? ' garage-registration-neutral' : '');
            }
            public function specificationLabel(\Automotive\Garage\Model\Vehicle $vehicle): string { return ''; }

            public function getUrl(string $route, array $params = []): string
            {
                return 'https://example.test/' . $route . ($params ? '?' . http_build_query($params) : '');
            }

            public function getBlockHtml(string $name): string
            {
                return $name === 'formkey' ? '<input type="hidden" name="form_key" value="test-key">' : '';
            }
        };
        $escaper = new class {
            public function escapeHtml(string $value): string
            {
                return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            }

            public function escapeHtmlAttr(string $value): string
            {
                return $this->escapeHtml($value);
            }

            public function escapeUrl(string $value): string
            {
                return $this->escapeHtml($value);
            }
        };
        ob_start();
        try {
            require dirname(__DIR__, 2) . '/view/frontend/templates/account.phtml';
            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }
}
