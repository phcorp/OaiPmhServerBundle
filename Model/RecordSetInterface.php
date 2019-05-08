<?php

declare(strict_types=1);

namespace Naoned\OaiPmhServerBundle\Model;

interface RecordSetInterface
{
    public function getIdentifier(): string;

    public function getName(): string;
}
