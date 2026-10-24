<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Trialing = 'trialing';
    case Pending = 'pending';
    case Incomplete = 'incomplete';
    case Active = 'active';
    case PastDue = 'past_due';
    case Grace = 'grace';
    case GracePeriod = 'grace_period';
    case CancelAtPeriodEnd = 'cancel_at_period_end';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case Suspended = 'suspended';

    /**
     * Determine if this subscription state allows access to system features.
     */
    public function allowsAccess(): bool
    {
        return match ($this) {
            self::Trialing, self::Active, self::Grace, self::GracePeriod => true,
            self::CancelAtPeriodEnd => true, // Allowed until current period ends (verified in model)
            self::Cancelled => true,         // Allowed until current period ends if current_period_end is future
            self::Pending, self::Incomplete, self::PastDue, self::Expired, self::Suspended => false,
        };
    }

    /**
     * Determine if transition to another status is allowed by the formal state machine.
     */
    public function canTransitionTo(self $target): bool
    {
        if ($this === $target) {
            return true;
        }

        return match ($this) {
            self::Trialing => in_array($target, [
                self::Active,
                self::PastDue,
                self::Grace,
                self::GracePeriod,
                self::CancelAtPeriodEnd,
                self::Cancelled,
                self::Expired,
                self::Suspended,
            ], true),

            self::Pending => in_array($target, [
                self::Active,
                self::Incomplete,
                self::Cancelled,
                self::Expired,
                self::Suspended,
            ], true),

            self::Incomplete => in_array($target, [
                self::Pending,
                self::Active,
                self::Cancelled,
                self::Expired,
                self::Suspended,
            ], true),

            self::Active => in_array($target, [
                self::CancelAtPeriodEnd,
                self::Cancelled,
                self::Grace,
                self::GracePeriod,
                self::PastDue,
                self::Expired,
                self::Suspended,
            ], true),

            self::CancelAtPeriodEnd => in_array($target, [
                self::Active,       // Resumed auto-renewal before period end
                self::Cancelled,    // Immediate cancellation requested
                self::Expired,      // Period elapsed without renewal
                self::Suspended,
            ], true),

            self::Grace, self::GracePeriod => in_array($target, [
                self::Active,       // Payment received
                self::PastDue,      // Grace window elapsed without payment
                self::Expired,
                self::Cancelled,
                self::Suspended,
            ], true),

            self::PastDue => in_array($target, [
                self::Active,       // Payment received
                self::Grace,
                self::GracePeriod,
                self::Expired,
                self::Cancelled,
                self::Suspended,
            ], true),

            self::Cancelled => in_array($target, [
                self::Active,       // Reactivated upon verified payment
                self::Expired,
                self::Suspended,
            ], true),

            self::Expired => in_array($target, [
                self::Active,       // Reactivated upon verified payment
                self::Suspended,
            ], true),

            self::Suspended => in_array($target, [
                self::Active,       // Admin reinstated after investigation
                self::Cancelled,
                self::Expired,
            ], true),
        };
    }

    /**
     * Human-readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Trialing => 'Փորձնական (Trial)',
            self::Pending => 'Սպասվող (Pending Payment)',
            self::Incomplete => 'Անավարտ (Incomplete)',
            self::Active => 'Ակտիվ (Active)',
            self::PastDue => 'Ժամկետանց (Past Due)',
            self::Grace, self::GracePeriod => 'Արտոնյալ ժամկետ (Grace Period)',
            self::CancelAtPeriodEnd => 'Չեղարկվելու է ժամկետի ավարտին (Cancel at Period End)',
            self::Cancelled => 'Չեղարկված (Cancelled)',
            self::Expired => 'Ավարտված (Expired)',
            self::Suspended => 'Կասեցված (Suspended)',
        };
    }
}
