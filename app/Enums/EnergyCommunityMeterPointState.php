<?php

declare(strict_types=1);

namespace App\Enums;

enum EnergyCommunityMeterPointState: string
{
    case New = 'new';
    case Requested = 'requested';
    case MessageReceived = 'message_received';
    case Accepted = 'accepted';
    case Error = 'error';
    case Removed = 'removed';
    case Deactivated = 'deactivated';

    /**
     * BR-7: these states hold a metering point's period against overlap checks;
     * error/removed/deactivated do not.
     */
    public function blocks(): bool
    {
        return match ($this) {
            self::New, self::Requested, self::MessageReceived, self::Accepted => true,
            self::Error, self::Removed, self::Deactivated => false,
        };
    }

    public function isTerminal(): bool
    {
        return $this === self::Removed || $this === self::Deactivated;
    }

    /**
     * BR-9 state machine. Everything not listed here is a conflict.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::New => [self::Requested, self::Removed],
            self::Requested => [self::MessageReceived, self::Error, self::Removed],
            self::MessageReceived => [self::Accepted, self::Error, self::Removed],
            self::Error => [self::Requested, self::Removed],
            self::Accepted => [self::Deactivated],
            self::Deactivated, self::Removed => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), strict: true);
    }

    /**
     * BR-10: "deleting" a registration maps to whichever BR-9 transition
     * ends its current state. Null means already terminal — nothing to
     * transition to.
     */
    public function deletionTarget(): ?self
    {
        return match ($this) {
            self::Accepted => self::Deactivated,
            self::New, self::Requested, self::MessageReceived, self::Error => self::Removed,
            self::Deactivated, self::Removed => null,
        };
    }
}
