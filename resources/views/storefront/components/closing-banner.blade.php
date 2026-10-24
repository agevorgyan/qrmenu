<!-- Operating Hours Closing Warning & Closed Alert Banner -->
<div x-cloak class="closing-banner-container" style="padding: 0 1rem; margin-top: 0.75rem; margin-bottom: 0.5rem;">
    <!-- 1. Closing Soon Warning Banner -->
    <template x-if="isCurrentChannelClosingSoon && !dismissedWarningBanner && currentSchedule">
        <div style="background: linear-gradient(135deg, rgba(245, 158, 11, 0.18), rgba(217, 119, 6, 0.22)); border: 1.5px solid rgba(245, 158, 11, 0.45); border-radius: 14px; padding: 0.75rem 1rem; display: flex; align-items: center; justify-content: space-between; gap: 0.75rem; box-shadow: 0 4px 15px rgba(245, 158, 11, 0.12); backdrop-filter: blur(8px);">
            <div style="display: flex; align-items: center; gap: 0.65rem; min-width: 0;">
                <span style="width: 32px; height: 32px; border-radius: 8px; background: #f59e0b; color: #ffffff; display: flex; align-items: center; justify-content: center; font-size: 0.95rem; flex-shrink: 0; box-shadow: 0 2px 8px rgba(245, 158, 11, 0.4);">
                    <i class="fa-solid fa-hourglass-half fa-spin" style="--fa-animation-duration: 3s;"></i>
                </span>
                <div style="min-width: 0;">
                    <div style="font-weight: 800; font-size: 0.85rem; color: #d97706; display: flex; align-items: center; gap: 0.35rem;" x-text="currentSchedule.notice_title || '{{ __('menu.closing_soon_notice_title') }}'"></div>
                    <div style="font-size: 0.75rem; color: var(--text-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" x-text="currentSchedule.notice_message"></div>
                </div>
            </div>
            <button type="button" @click="dismissedWarningBanner = true" style="background: transparent; border: none; color: var(--text-muted); cursor: pointer; padding: 0.3rem 0.45rem; font-size: 0.85rem; border-radius: 6px;" title="{{ __('menu.close') }}">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
    </template>

    <!-- 2. Currently Closed Alert Banner -->
    <template x-if="!isCurrentChannelOpen && currentSchedule">
        <div style="background: linear-gradient(135deg, rgba(239, 68, 68, 0.16), rgba(220, 38, 38, 0.2)); border: 1.5px solid rgba(239, 68, 68, 0.4); border-radius: 14px; padding: 0.75rem 1rem; display: flex; align-items: center; gap: 0.75rem; box-shadow: 0 4px 15px rgba(239, 68, 68, 0.1); backdrop-filter: blur(8px);">
            <span style="width: 32px; height: 32px; border-radius: 8px; background: #ef4444; color: #ffffff; display: flex; align-items: center; justify-content: center; font-size: 0.95rem; flex-shrink: 0; box-shadow: 0 2px 8px rgba(239, 68, 68, 0.4);">
                <i class="fa-solid fa-door-closed"></i>
            </span>
            <div style="min-width: 0; flex: 1;">
                <div style="font-weight: 800; font-size: 0.85rem; color: #ef4444;" x-text="currentSchedule.notice_title || '{{ __('menu.service_closed_notice_title') }}'"></div>
                <div style="font-size: 0.75rem; color: var(--text-muted);" x-text="currentSchedule.notice_message"></div>
            </div>
        </div>
    </template>
</div>
