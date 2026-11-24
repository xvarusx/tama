<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ContactStatus;
use App\Enum\ContactType;
use App\Event\ContactBlacklisted;
use App\Event\ContactDeactivated;
use App\Event\ContactReinstated;
use App\Event\ContactUpdated;
use App\Repository\ContactRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Ignore;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ContactRepository::class)]
#[ORM\Table(name: 'contacts')]
#[ORM\Index(name: 'idx_contact_type', fields: ['contactType'])]
#[ORM\Index(name: 'idx_contact_status', fields: ['status'])]
#[ORM\UniqueConstraint(name: 'uniq_contact_tax_number_type', fields: ['taxNumber', 'contactType'])]
class Contact
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
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

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $blacklistReason = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $blacklistedAt = null;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $createdBy = null;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $updatedBy = null;

    /** User ID from the authentication service. */
    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $blacklistedBy = null;

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

    /** @var array<string> Field names that have been modified since last flush. */
    private array $changedFields = [];

    public function __construct(string $name, Uuid $createdBy, ContactType $contactType = ContactType::CLIENT)
    {
        $this->id = Uuid::v7();
        $this->name = $name;
        $this->contactType = $contactType;
        $this->createdBy = $createdBy;
        $this->updatedBy = $createdBy;
        $this->createdAt = $this->updatedAt = new \DateTimeImmutable();
    }

    // ---- Status management (use these instead of setters, they keep the data consistent) ----

    public function blacklist(string $reason, Uuid $by): void
    {
        if (ContactStatus::BLACKLISTED === $this->status) {
            return;
        }
        if ('' === trim($reason)) {
            throw new \DomainException('A reason is required to blacklist a contact.');
        }

        $this->status = ContactStatus::BLACKLISTED;
        $this->blacklistReason = $reason;
        $this->blacklistedAt = new \DateTimeImmutable();
        $this->blacklistedBy = $by;
        $this->touch($by);

        $this->record(new ContactBlacklisted($this, $reason));
    }

    public function reinstate(Uuid $by): void
    {
        if (ContactStatus::BLACKLISTED !== $this->status) {
            throw new \DomainException('Only a blacklisted contact can be reinstated.');
        }

        $this->status = ContactStatus::ACTIVE;
        $this->blacklistReason = null;
        $this->blacklistedAt = null;
        $this->blacklistedBy = null;
        $this->touch($by);

        $this->record(new ContactReinstated($this));
    }

    public function deactivate(Uuid $by): void
    {
        if (ContactStatus::BLACKLISTED === $this->status) {
            throw new \DomainException('Reinstate the contact before deactivating it.');
        }
        if (ContactStatus::INACTIVE === $this->status) {
            return;
        }

        $this->status = ContactStatus::INACTIVE;
        $this->touch($by);

        $this->record(new ContactDeactivated($this));
    }

    public function activate(Uuid $by): void
    {
        if (ContactStatus::INACTIVE !== $this->status) {
            throw new \DomainException('Only an inactive contact can be activated.');
        }

        $this->status = ContactStatus::ACTIVE;
        $this->touch($by);
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

    private function recordChangedField(string $fieldName): void
    {
        if (!in_array($fieldName, $this->changedFields, true)) {
            $this->changedFields[] = $fieldName;
        }
    }

    /**
     * Call this after all changes have been made to emit ContactUpdated event
     * if any regular fields were modified.
     */
    public function emitUpdatedEvent(): void
    {
        if (!empty($this->changedFields)) {
            $this->record(new ContactUpdated($this, $this->changedFields));
            $this->changedFields = [];
        }
    }

    public function markUpdatedBy(Uuid $userId): void
    {
        $this->touch($userId);
    }

    private function touch(?Uuid $userId = null): void
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
        if ($this->name !== $name) {
            $this->name = $name;
            $this->touch();
            $this->recordChangedField('name');
        }

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        if ($this->email !== $email) {
            $this->email = $email;
            $this->touch();
            $this->recordChangedField('email');
        }

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        if ($this->phone !== $phone) {
            $this->phone = $phone;
            $this->touch();
            $this->recordChangedField('phone');
        }

        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        if ($this->address !== $address) {
            $this->address = $address;
            $this->touch();
            $this->recordChangedField('address');
        }

        return $this;
    }

    public function getTaxNumber(): ?string
    {
        return $this->taxNumber;
    }

    public function setTaxNumber(?string $taxNumber): static
    {
        // An empty string would collide in the unique index, so store it as NULL.
        $normalized = (null === $taxNumber || '' === trim($taxNumber)) ? null : trim($taxNumber);

        if ($this->taxNumber !== $normalized) {
            $this->taxNumber = $normalized;
            $this->touch();
            $this->recordChangedField('taxNumber');
        }

        return $this;
    }

    // ---- Read-only state ----

    public function getContactType(): ContactType
    {
        return $this->contactType;
    }

    public function setContactType(ContactType $contactType): static
    {
        if ($this->contactType !== $contactType) {
            $this->contactType = $contactType;
            $this->touch();
            $this->recordChangedField('contactType');
        }

        return $this;
    }

    public function getStatus(): ContactStatus
    {
        return $this->status;
    }

    public function getBlacklistReason(): ?string
    {
        return $this->blacklistReason;
    }

    public function getBlacklistedAt(): ?\DateTimeImmutable
    {
        return $this->blacklistedAt;
    }

    public function getCreatedBy(): ?Uuid
    {
        return $this->createdBy;
    }

    public function getUpdatedBy(): ?Uuid
    {
        return $this->updatedBy;
    }

    public function getBlacklistedBy(): ?Uuid
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
