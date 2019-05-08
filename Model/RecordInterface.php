<?php

declare(strict_types=1);

namespace Naoned\OaiPmhServerBundle\Model;

use DateTimeInterface;

interface RecordInterface
{
    public function getIdentifier(): string;

    public function getTitle(): string;

    public function getDescription(): string;

    public function getLastChange(): DateTimeInterface;

    /**
     * @return RecordSetInterface[]
     */
    public function getSets(): iterable;
}
