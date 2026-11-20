<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Contact;
use App\Enum\ContactStatus;
use App\Enum\ContactType;
use App\Event\ContactBlacklisted;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class ContactTest extends TestCase
{
    public function testContactHasUuidId(): void
    {
        $contact = new Contact('Test Contact', $this->uid(1), ContactType::CLIENT);

        self::assertInstanceOf(Uuid::class, $contact->getId());
        self::assertTrue(Uuid::isValid((string) $contact->getId()));
    }

    public function testBlacklistRecordsTheActorAndEmitsAnEvent(): void
    {
        $contact = new Contact('Northwind Traders', $this->uid(12), ContactType::SUPPLIER);
        $actor = $this->uid(34);

        $contact->blacklist('Repeated late payments', $actor);

        self::assertSame(ContactStatus::BLACKLISTED, $contact->getStatus());
        self::assertSame($actor, $contact->getBlacklistedBy());
        self::assertSame($actor, $contact->getUpdatedBy());
        $events = $contact->pullEvents();
        self::assertContainsOnlyInstancesOf(ContactBlacklisted::class, [$events[0]]);
        self::assertSame([], $contact->pullEvents());
    }

    public function testReinstatementAndDeactivationRecordTheLatestActor(): void
    {
        $contact = new Contact('Acme Supplies', $this->uid(1));
        $contact->blacklist('Unresolved dispute', $this->uid(2));
        $contact->pullEvents();

        $reinstateActor = $this->uid(3);
        $contact->reinstate($reinstateActor);
        self::assertSame(ContactStatus::ACTIVE, $contact->getStatus());
        self::assertNull($contact->getBlacklistedBy());
        self::assertSame($reinstateActor, $contact->getUpdatedBy());

        $deactivateActor = $this->uid(4);
        $contact->deactivate($deactivateActor);
        self::assertSame(ContactStatus::INACTIVE, $contact->getStatus());
        self::assertSame($deactivateActor, $contact->getUpdatedBy());
    }

    public function testBlacklistedContactsCannotBeDeactivated(): void
    {
        $contact = new Contact('Acme Supplies', $this->uid(1));
        $contact->blacklist('Unresolved dispute', $this->uid(2));

        $this->expectException(\DomainException::class);
        $contact->deactivate($this->uid(3));
    }

    public function testContactDefaultsToActiveStatus(): void
    {
        $contact = new Contact('Test', $this->uid(1), ContactType::CLIENT);

        self::assertSame(ContactStatus::ACTIVE, $contact->getStatus());
    }

    public function testContactTypeIsSetInConstructor(): void
    {
        $client = new Contact('Client', $this->uid(1), ContactType::CLIENT);
        $supplier = new Contact('Supplier', $this->uid(1), ContactType::SUPPLIER);

        self::assertSame(ContactType::CLIENT, $client->getContactType());
        self::assertSame(ContactType::SUPPLIER, $supplier->getContactType());
    }

    public function testActivateInactiveContact(): void
    {
        $contact = new Contact('Test', $this->uid(1));
        $contact->deactivate($this->uid(2));
        $contact->pullEvents();

        $actor = $this->uid(3);
        $contact->activate($actor);

        self::assertSame(ContactStatus::ACTIVE, $contact->getStatus());
        self::assertSame($actor, $contact->getUpdatedBy());
    }

    public function testCannotActivateNonInactiveContact(): void
    {
        $contact = new Contact('Test', $this->uid(1));

        $this->expectException(\DomainException::class);
        $contact->activate($this->uid(2));
    }

    public function testBlacklistSetsBlacklistReasonAndTimestamp(): void
    {
        $contact = new Contact('Test', $this->uid(1));
        $actor = $this->uid(2);
        $contact->blacklist('Bad behavior', $actor);

        self::assertSame('Bad behavior', $contact->getBlacklistReason());
        self::assertNotNull($contact->getBlacklistedAt());
        self::assertSame($actor, $contact->getBlacklistedBy());
    }

    public function testReinstateClearsBlacklistFields(): void
    {
        $contact = new Contact('Test', $this->uid(1));
        $contact->blacklist('Bad behavior', $this->uid(2));
        $contact->pullEvents();

        $actor = $this->uid(3);
        $contact->reinstate($actor);

        self::assertNull($contact->getBlacklistReason());
        self::assertNull($contact->getBlacklistedAt());
        self::assertNull($contact->getBlacklistedBy());
        self::assertSame($actor, $contact->getUpdatedBy());
    }

    private function uid(int $number): Uuid
    {
        return Uuid::v5(Uuid::fromString('6ba7b811-9dad-11d1-80b4-00c04fd430c8'), 'test-user-' . $number);
    }
}
