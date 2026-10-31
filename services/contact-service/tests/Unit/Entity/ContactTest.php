<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Contact;
use App\Enum\ContactStatus;
use App\Enum\ContactType;
use App\Enum\TrustLevel;
use App\Event\ContactBlacklisted;
use App\Event\ContactTrustLevelChanged;
use PHPUnit\Framework\TestCase;

final class ContactTest extends TestCase
{
    public function testBlacklistRecordsTheActorAndEmitsAnEvent(): void
    {
        $contact = new Contact('Northwind Traders', 12, ContactType::SUPPLIER);
        $contact->changeTrustLevel(TrustLevel::TRUSTED, 12);

        $contact->blacklist('Repeated late payments', 34);

        self::assertSame(ContactStatus::BLACKLISTED, $contact->getStatus());
        self::assertSame(TrustLevel::UNRATED, $contact->getTrustLevel());
        self::assertSame(34, $contact->getBlacklistedBy());
        self::assertSame(34, $contact->getUpdatedBy());
        $events = $contact->pullEvents();
        self::assertContainsOnlyInstancesOf(ContactTrustLevelChanged::class, [$events[0]]);
        self::assertContainsOnlyInstancesOf(ContactBlacklisted::class, [$events[1]]);
        self::assertSame([], $contact->pullEvents());
    }

    public function testReinstatementAndDeactivationRecordTheLatestActor(): void
    {
        $contact = new Contact('Acme Supplies', 1);
        $contact->blacklist('Unresolved dispute', 2);
        $contact->pullEvents();

        $contact->reinstate(3);
        self::assertSame(ContactStatus::ACTIVE, $contact->getStatus());
        self::assertNull($contact->getBlacklistedBy());
        self::assertSame(3, $contact->getUpdatedBy());

        $contact->deactivate(4);
        self::assertSame(ContactStatus::INACTIVE, $contact->getStatus());
        self::assertSame(4, $contact->getUpdatedBy());
    }

    public function testBlacklistedContactsCannotBeDeactivated(): void
    {
        $contact = new Contact('Acme Supplies', 1);
        $contact->blacklist('Unresolved dispute', 2);

        $this->expectException(\DomainException::class);
        $contact->deactivate(3);
    }
}
