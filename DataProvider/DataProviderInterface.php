<?php

declare(strict_types=1);

namespace Naoned\OaiPmhServerBundle\DataProvider;

use DateTimeInterface;
use Naoned\OaiPmhServerBundle\Model\RecordInterface;

interface DataProviderInterface
{
    /**
     * Get identifier of id.
     *
     * @param RecordInterface $record An item of elements furnished by getRecords method
     *
     * @return string Record Id
     */
    public static function getRecordId(RecordInterface $record): string;

    /**
     * Get last change date.
     *
     * @param RecordInterface $record An item of elements furnished by getRecords method
     *
     * @return DateTimeInterface Record last change
     */
    public static function getRecordUpdated(RecordInterface $record): DateTimeInterface;

    /**
     * @return string Repository name
     */
    public function getRepositoryName(): string;

    /**
     * @return string Repository admin email
     */
    public function getAdminEmail(): string;

    /**
     * @return \DateTimeInterface Repository earliest update change on data
     */
    public function getEarliestDatestamp(): DateTimeInterface;

    /**
     * @param string $id Record identifier
     *
     * @return RecordInterface|null
     */
    public function getRecord(string $id): ?RecordInterface;

    /**
     * Search for records.
     *
     * @param string|null            $setTitle Title of wanted set
     * @param DateTimeInterface|null $from Date of last change «from»
     * @param DateTimeInterface|null $until Date of last change «until»
     *
     * @return array|RecordInterface[] List of items
     */
    public function getRecords(?string $setTitle = null, ?DateTimeInterface $from = null, ?DateTimeInterface $until = null): array;

    /**
     * Returns an array of arrays with keys «identifier» and «name».
     *
     * @return array List of all sets, with identifier and name
     */
    public function getSets(): array;

    /**
     * Tell me, this «record», in which «set» is it?
     *
     * @param RecordInterface $record An item of elements furnished by getRecords method
     *
     * @return array List of the sets the record belongs to
     */
    public function getSetsForRecord(RecordInterface $record): array;

    /**
     * Transform the provided record in an array with Dublin Core, «dc_title» style.
     *
     * @param RecordInterface $record An item of elements furnished by getRecords method
     *
     * @return array Dublin core data
     */
    public function dublinizeRecord(RecordInterface $record): array;

    /**
     * Check if sets are supported by data provider.
     *
     * @return boolean check
     */
    public function checkSupportSets(): bool;
}
