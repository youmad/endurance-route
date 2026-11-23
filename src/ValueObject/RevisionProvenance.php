<?php

declare(strict_types=1);

namespace Youmad\Endurance\Route\ValueObject;

final readonly class RevisionProvenance
{
    public function __construct(
        public string $origin = 'unknown',
        public ?string $sourceReference = null,
        public ?string $sourceRevision = null,
        public ?string $interpretationVersion = null,
    ) {
        foreach ([$origin, $sourceReference, $sourceRevision, $interpretationVersion] as $value) {
            if (null !== $value && '' === trim($value)) {
                throw new \InvalidArgumentException('Known provenance identifiers must not be blank.');
            }
        }
    }
}
