<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ContactStatus;
use App\Enum\ContactType;
use App\Enum\TrustLevel;
use App\Event\ContactBlacklisted;
use App\Event\ContactDeactivated;
use App\Event\ContactReinstated;
use App\Event\ContactTrustLevelChanged;
use App\Repository\ContactRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Types\UuidType;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ContactRepository::class)]
#[ORM\Table(name: 'contacts')]
#[ORM\Index(name: 'idx_contact_type', fields: ['contactType'])]
#[ORM\Index(name: 'idx_contact_status', fields: ['status'])]
#[ORM\Index(name: 'idx_contact_trust_level', fields: ['trustLevel'])]
#[ORM\UniqueConstraint(name: 'uniq_contact_tax_number_type', fields: ['taxNumber', 'contactType'])]
class Contact
{
    #[ORM\Id]
    #[ORM\Column(type: UuidType::NAME)]
    private Uuid $id;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $taxNumber = null;

    #[Assert\NotBlank]
    #[ORM\Column(length: 255)]
    private string $name;

    #[Assert\Email]
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(enumType: ContactType::class, length: 16)]
    private ContactType $contactType;

    #[ORM\Column(enumType: ContactStatus::class, length: 16)]
    private ContactStatus $status = ContactStatus::ACTIVE;

    #[ORM\Column(enumType: TrustLevel::class, length: 16)]
    private TrustLevel $trustLevel = TrustLevel::UNRATED;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $blacklistReason = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $blacklistedAt = null;

    #[ORM\Column(nullable: true)]
    private ?int $createdBy;

    #[ORM\Column(nullable: true)]
    private ?int $updatedBy;

    /** User ID from the authentication service. */
    #[ORM\Column(nullable: true)]
    private ?int $blacklistedBy = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    /** Optimistic locking: two users cannot overwrite each other's changes silently. */
    #[ORM\Version]
    #[ORM\Column(type: Types::INTEGER)]
    private int $version = 1;

    /** @var list<object> Domain events, dispatched after flush. Not persisted. */
    private array $events = [];

    public function __construct(string $name, int $createdBy, ContactType $contactType = ContactType::CLIENT)
    {
        $this->id = Uuid::v7();
        $this->name = $name;
        $this->contactType = $contactType;
        $this->createdBy = $createdBy;
        $this->updatedBy = $createdBy;
        $this->createdAt = $this->updatedAt = new \DateTimeImmutable();
    }

    // ---- Status management (use these instead of setters, they keep the data consistent) ----

    public function blacklist(string $reason, int $by): void
    {
        if (ContactStatus::BLACKLISTED === $this->status) {
            return;
        }
        if ('' === trim($reason)) {
            throw new \DomainException('A reason is required to blacklist a contact.');
        }

        $this->status = ContactStatus::BLACKLISTED;
        $this->trustLevel = TrustLevel::UNRATED; // a blacklisted contact cannot stay trusted
        $this->blacklistReason = $reason;
        $this->blacklistedAt = new \DateTimeImmutable();
        $this->blacklistedBy = $by;
        $this->touch($by);

        $this->record(new ContactBlacklisted($this->id, $this->contactType, $reason));
    }

    public function reinstate(int $by): void
    {
        if (ContactStatus::BLACKLISTED !== $this->status) {
            throw new \DomainException('Only a blacklisted contact can be reinstated.');
        }

        $this->status = ContactStatus::ACTIVE;
        $this->blacklistReason = null;
        $this->blacklistedAt = null;
        $this->blacklistedBy = null;
        $this->touch($by);

        $this->record(new ContactReinstated($this->id, $this->contactType));
    }

    public function deactivate(int $by): void
    {
        if (ContactStatus::BLACKLISTED === $this->status) {
            throw new \DomainException('Reinstate the contact before deactivating it.');
        }
        if (ContactStatus::INACTIVE === $this->status) {
            return;
        }

        $this->status = ContactStatus::INACTIVE;
        $this->touch($by);

        $this->record(new ContactDeactivated($this->id, $this->contactType));
    }

    public function activate(int $by): void
    {
        if (ContactStatus::INACTIVE !== $this->status) {
            throw new \DomainException('Only an inactive contact can be activated.');
        }

        $this->status = ContactStatus::ACTIVE;
        $this->touch($by);
    }

    public function changeTrustLevel(TrustLevel $trustLevel, int $by): void
    {
        if (ContactStatus::BLACKLISTED === $this->status) {
            throw new \DomainException('The trust level of a blacklisted contact cannot be changed.');
        }
        if ($trustLevel === $this->trustLevel) {
            return;
        }

        $this->trustLevel = $trustLevel;
        $this->touch($by);

        $this->record(new ContactTrustLevelChanged($this->id, $this->contactType, $trustLevel));
    }

    /** Can new orders be created with this contact? */
    #[Ignore]
    public function canTransact(): bool
    {
        return ContactStatus::ACTIVE === $this->status;
    }

    #[Ignore]
    public function isBlacklisted(): bool
    {
        return ContactStatus::BLACKLISTED === $this->status;
    }

    #[Ignore]
    public function isTrusted(): bool
    {
        return TrustLevel::TRUSTED === $this->trustLevel;
    }

    // ---- Events ----

    /** @return list<object> */
    public function pullEvents(): array
    {
        $events = $this->events;
        $this->events = [];

        return $events;
    }

    private function record(object $event): void
    {
        $this->events[] = $event;
    }

    public function markUpdatedBy(int $userId): void
    {
        $this->touch($userId);
    }

    private function touch(?int $userId = null): void
    {
        $this->updatedAt = new \DateTimeImmutable();

        if (null !== $userId) {
            $this->updatedBy = $userId;
        }
    }

    // ---- Plain details (safe to edit freely) ----

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        $this->touch();

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;
        $this->touch();

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;
        $this->touch();

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = $address;
        $this->touch();

        return $this;
    }

    public function getTaxNumber(): ?string
    {
        return $this->taxNumber;
    }

    public function setTaxNumber(?string $taxNumber): static
    {
        // An empty string would collide in the unique index, so store it as NULL.
        $this->taxNumber = (null === $taxNumber || '' === trim($taxNumber)) ? null : trim($taxNumber);
        $this->touch();

        return $this;
    }

    // ---- Read-only state ----

    public function getContactType(): ContactType
    {
        return $this->contactType;
    }

    public function getStatus(): ContactStatus
    {
        return $this->status;
    }

    public function getTrustLevel(): TrustLevel
    {
        return $this->trustLevel;
    }

    public function getBlacklistReason(): ?string
    {
        return $this->blacklistReason;
    }

    public function getBlacklistedAt(): ?\DateTimeImmutable
    {
        return $this->blacklistedAt;
    }

    public function getCreatedBy(): ?int
    {
        return $this->createdBy;
    }

    public function getUpdatedBy(): ?int
    {
        return $this->updatedBy;
    }

    public function getBlacklistedBy(): ?int
    {
        return $this->blacklistedBy;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function getVersion(): int
    {
        return $this->version;
    }
}
