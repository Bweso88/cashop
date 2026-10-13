<?php

namespace App\Domain\Providers\Connectors\DTO;

/**
 * Champ demandé par un réseau pour un corridor. La liste officielle vient de la documentation du
 * prestataire ; le connecteur simulé n'expose qu'un minimum générique, marqué comme tel.
 */
final readonly class FieldSpec
{
    public function __construct(
        public string $name,
        public string $label,
        public bool $required,
        public string $type = 'text',
        public string $source = 'SIMULATION',
    ) {}

    public function toArray(): array
    {
        return ['name' => $this->name, 'label' => $this->label, 'required' => $this->required, 'type' => $this->type, 'source' => $this->source];
    }
}
