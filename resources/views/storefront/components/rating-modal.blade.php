<!-- Service & Waiter Rating Modal (Full-Screen / Mobile-First Bottom Sheet) -->
<div x-show="showRatingModal" 
     x-cloak
     x-transition:enter="transition ease-out duration-250"
     x-transition:enter-start="opacity-0 transform translate-y-4"
     x-transition:enter-end="opacity-100 transform translate-y-0"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100 transform translate-y-0"
     x-transition:leave-end="opacity-0 transform translate-y-4"
     style="position: fixed; inset: 0; width: 100%; height: 100%; height: 100dvh; background: var(--bg-card); z-index: 220; display: flex; flex-direction: column; overflow: hidden;"
     role="dialog"
     aria-modal="true"
     aria-labelledby="ratingModalTitle">
    
    <div style="background: var(--bg-card); width: 100%; max-width: 640px; margin: 0 auto; height: 100%; height: 100dvh; display: flex; flex-direction: column; box-sizing: border-box;">
        
        <!-- Sticky Top Header -->
        <div style="padding: max(1rem, env(safe-area-inset-top)) 1.25rem 0.85rem 1.25rem; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); background: var(--bg-card); position: sticky; top: 0; z-index: 10;">
            <div style="display: flex; align-items: center; gap: 0.65rem;">
                <div style="width: 38px; height: 38px; border-radius: 12px; background: rgba(245, 158, 11, 0.15); color: #f59e0b; display: flex; align-items: center; justify-content: center; font-size: 1.15rem; flex-shrink: 0;">
                    <i class="fa-solid fa-star"></i>
                </div>
                <div>
                    <h3 id="ratingModalTitle" style="font-family: 'Outfit', sans-serif; font-size: 1.25rem; font-weight: 800; color: var(--text-main); margin: 0; line-height: 1.2;">
                        {{ __('tips.rate_service_title') ?? 'Ինչպե՞ս էր ձեր փորձը' }}
                    </h3>
                    <p style="font-size: 0.78rem; color: var(--text-muted); margin: 0.15rem 0 0 0;">
                        {{ __('tips.rate_service_subtitle') ?? 'Ձեր կարծիքն օգնում է մեզ էլ ավելի կատարելագործել սպասարկումը' }}
                    </p>
                </div>
            </div>
            <button type="button" 
                    @click="showRatingModal = false" 
                    style="background: var(--bg-main); border: 1px solid var(--border-color); color: var(--text-main); width: 38px; height: 38px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.1rem; cursor: pointer; flex-shrink: 0;"
                    aria-label="{{ __('tips.close') ?? 'Փակել' }}">
                ✕
            </button>
        </div>

        <!-- Scrollable Content -->
        <div style="flex: 1; overflow-y: auto; -webkit-overflow-scrolling: touch; padding: 1.25rem 1.25rem calc(2.5rem + env(safe-area-inset-bottom)) 1.25rem; display: flex; flex-direction: column; gap: 1.25rem;">
            
            <!-- Success Screen After Rating Submitted -->
            <template x-if="ratingSubmitted">
                <div style="text-align: center; padding: 2.5rem 1rem; display: flex; flex-direction: column; align-items: center; gap: 1rem;">
                    <div style="width: 72px; height: 72px; border-radius: 50%; background: rgba(16, 185, 129, 0.15); color: #10b981; display: flex; align-items: center; justify-content: center; font-size: 2.2rem;">
                        <i class="fa-solid fa-check"></i>
                    </div>
                    <div>
                        <h4 style="font-family: 'Outfit', sans-serif; font-size: 1.4rem; font-weight: 800; color: var(--text-main); margin: 0;">
                            {{ __('tips.thank_you_rating_title') ?? 'Շնորհակալություն գնահատականի համար!' }}
                        </h4>
                        <p style="font-size: 0.9rem; color: var(--text-muted); margin-top: 0.5rem; max-width: 320px;">
                            {{ __('tips.thank_you_rating_desc') ?? 'Ձեր արձագանքը հաջողությամբ գրանցվել է և շատ արժեքավոր է մեր թիմի համար:' }}
                        </p>
                    </div>
                    <button type="button" 
                            @click="showRatingModal = false; ratingSubmitted = false;" 
                            style="margin-top: 1rem; padding: 0.85rem 2rem; background: var(--primary); color: #fff; border: none; border-radius: 12px; font-weight: 700; cursor: pointer;">
                        {{ __('tips.done') ?? 'Պատրաստ է' }}
                    </button>
                </div>
            </template>

            <!-- Form Content -->
            <template x-if="!ratingSubmitted">
                <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                    
                    <!-- Order Reference Badge (if available) -->
                    <template x-if="ratingOrderNumber">
                        <div style="background: var(--bg-main); border: 1px solid var(--border-color); border-radius: 12px; padding: 0.65rem 0.95rem; display: flex; justify-content: space-between; align-items: center;">
                            <span style="font-size: 0.8rem; color: var(--text-muted);">{{ __('tips.order_reference') ?? 'Պատվերի համար' }}</span>
                            <span style="font-family: 'Outfit'; font-weight: 800; color: var(--primary); font-size: 0.9rem;" x-text="ratingOrderNumber"></span>
                        </div>
                    </template>

                    <!-- Interactive Star Rating Scale -->
                    <div style="background: var(--bg-main); border: 1px solid var(--border-color); border-radius: 18px; padding: 1.25rem; text-align: center;">
                        <div style="font-size: 0.85rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.85rem; text-transform: uppercase; letter-spacing: 0.05em;">
                            {{ __('tips.select_rating') ?? 'Ընտրեք գնահատականը' }}
                        </div>
                        <div style="display: flex; justify-content: center; align-items: center; gap: 0.65rem;" role="radiogroup" aria-label="Star Rating">
                            <template x-for="star in (tipsRatingsSettings.rating_scale || 5)" :key="star">
                                <button type="button" 
                                        @click="ratingScore = star"
                                        style="background: transparent; border: none; cursor: pointer; padding: 0.25rem; font-size: 2.2rem; transition: transform 0.15s ease;"
                                        :style="ratingScore >= star ? 'color: #f59e0b; transform: scale(1.1);' : 'color: rgba(156, 163, 175, 0.4);'"
                                        :aria-label="star + ' stars'"
                                        :aria-checked="ratingScore === star">
                                    <i class="fa-solid fa-star"></i>
                                </button>
                            </template>
                        </div>
                        <div style="margin-top: 0.75rem; font-weight: 700; font-size: 0.95rem; color: var(--text-main);">
                            <span x-text="ratingScore === 5 ? '{{ __('tips.rating_5_stars') ?? 'Գերազանց' }}' : (ratingScore === 4 ? '{{ __('tips.rating_4_stars') ?? 'Շատ լավ' }}' : (ratingScore === 3 ? '{{ __('tips.rating_3_stars') ?? 'Լավ' }}' : (ratingScore === 2 ? '{{ __('tips.rating_2_stars') ?? 'Բավարար' }}' : '{{ __('tips.rating_1_star') ?? 'Վատ' }}')))"></span>
                        </div>
                    </div>

                    <!-- Predefined Feedback Categories -->
                    <template x-if="tipsRatingsSettings.feedback_categories && tipsRatingsSettings.feedback_categories.length > 0">
                        <div>
                            <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); margin-bottom: 0.6rem; display: block; text-transform: uppercase; letter-spacing: 0.04em;">
                                {{ __('tips.what_went_well') ?? 'Ի՞նչն էր առանձնահատուկ' }}
                            </label>
                            <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                                <template x-for="cat in tipsRatingsSettings.feedback_categories" :key="cat">
                                    <button type="button" 
                                            @click="toggleRatingCategory(cat)"
                                            style="padding: 0.5rem 0.85rem; border-radius: 10px; font-size: 0.8rem; font-weight: 700; cursor: pointer; transition: all 0.2s;"
                                            :style="ratingCategories.includes(cat) ? 'background: rgba(245, 158, 11, 0.15); border: 1.5px solid #f59e0b; color: #d97706;' : 'background: var(--bg-main); border: 1.5px solid var(--border-color); color: var(--text-main);'">
                                        <span x-text="formatCategoryName(cat)"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                    </template>

                    <!-- Waiter Attribution (Visual Avatar Cards) -->
                    <template x-if="tipsRatingsSettings.allow_waiter_selection && tipsRatingsSettings.waiters && tipsRatingsSettings.waiters.length > 0 && (!tipsRatingsSettings.waiter_selection_stage || tipsRatingsSettings.waiter_selection_stage === 'all' || tipsRatingsSettings.waiter_selection_stage === 'rating')">
                        <div>
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.45rem;">
                                <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); display: flex; align-items: center; gap: 0.4rem; margin: 0;">
                                    <i class="fa-solid fa-user-tie" style="color: var(--primary);"></i>
                                    <span>{{ __('tips.waiter_attribution') ?? 'Սպասարկող մատուցող' }}</span>
                                </label>
                                <template x-if="tipsRatingsSettings.waiter_selection_required">
                                    <span style="font-size: 0.72rem; color: #ef4444; font-weight: 700;">({{ __('tips.required') ?? 'պարտադիր' }})</span>
                                </template>
                            </div>

                            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 0.5rem;">
                                <!-- General Service / Skip Option -->
                                <template x-if="!tipsRatingsSettings.waiter_selection_required">
                                    <div @click="ratingWaiterId = null"
                                         :style="ratingWaiterId === null ? 'border-color: var(--primary); background: rgba(245, 158, 11, 0.08); box-shadow: 0 4px 12px rgba(245, 158, 11, 0.15);' : 'border-color: var(--border-color); background: var(--bg-main);'"
                                         style="cursor: pointer; border-radius: 12px; padding: 0.6rem 0.35rem; text-align: center; transition: all 0.2s ease; border: 1.5px solid var(--border-color); position: relative;">
                                        <template x-if="ratingWaiterId === null">
                                            <div style="position: absolute; top: 0.25rem; right: 0.25rem; width: 16px; height: 16px; border-radius: 50%; background: var(--primary); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 0.6rem;">
                                                <i class="fa-solid fa-check"></i>
                                            </div>
                                        </template>
                                        <div style="width: 38px; height: 38px; border-radius: 50%; margin: 0 auto 0.3rem; background: rgba(107, 114, 128, 0.12); color: var(--text-muted); display: flex; align-items: center; justify-content: center; font-size: 0.95rem;">
                                            <i class="fa-solid fa-users"></i>
                                        </div>
                                        <div style="font-size: 0.78rem; font-weight: 700; color: var(--text-main); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                            {{ __('Not sure / Skip') }}
                                        </div>
                                        <div style="font-size: 0.65rem; color: var(--text-muted); margin-top: 0.1rem;">
                                            {{ __('tips.restaurant_service') ?? 'Սրահի աշխատանք' }}
                                        </div>
                                    </div>
                                </template>

                                <!-- Waiter Cards -->
                                <template x-for="waiter in tipsRatingsSettings.waiters" :key="waiter.id">
                                    <div @click="ratingWaiterId = waiter.id"
                                         :style="ratingWaiterId === waiter.id ? 'border-color: var(--primary); background: rgba(245, 158, 11, 0.08); box-shadow: 0 4px 12px rgba(245, 158, 11, 0.2);' : 'border-color: var(--border-color); background: var(--bg-main);'"
                                         style="cursor: pointer; border-radius: 12px; padding: 0.6rem 0.35rem; text-align: center; transition: all 0.2s ease; border: 1.5px solid var(--border-color); position: relative;">
                                        <template x-if="ratingWaiterId === waiter.id">
                                            <div style="position: absolute; top: 0.25rem; right: 0.25rem; width: 16px; height: 16px; border-radius: 50%; background: var(--primary); color: #fff; display: flex; align-items: center; justify-content: center; font-size: 0.6rem;">
                                                <i class="fa-solid fa-check"></i>
                                            </div>
                                        </template>
                                        <div style="width: 40px; height: 40px; border-radius: 50%; margin: 0 auto 0.3rem; overflow: hidden; display: flex; align-items: center; justify-content: center; background: rgba(245, 158, 11, 0.15); border: 2px solid var(--primary);">
                                            <template x-if="waiter.photo_url">
                                                <img :src="waiter.photo_url" :alt="waiter.name" style="width: 100%; height: 100%; object-fit: cover;" loading="lazy">
                                            </template>
                                            <template x-if="!waiter.photo_url">
                                                <span style="font-weight: 800; font-size: 0.95rem; color: var(--primary);" x-text="waiter.initial || waiter.name.charAt(0)"></span>
                                            </template>
                                        </div>
                                        <div style="font-size: 0.8rem; font-weight: 700; color: var(--text-main); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" x-text="waiter.name"></div>
                                        <template x-if="waiter.short_description">
                                            <div style="font-size: 0.65rem; color: var(--text-muted); margin-top: 0.1rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" x-text="waiter.short_description"></div>
                                        </template>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>

                    <!-- Written Comment -->
                    <div>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.4rem;">
                            <label style="font-size: 0.8rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.04em;">
                                {{ __('tips.your_feedback') ?? 'Ձեր մեկնաբանությունը' }}
                            </label>
                            <span style="font-size: 0.72rem; color: var(--text-muted);" x-text="tipsRatingsSettings.is_written_feedback_optional ? '({{ __('tips.optional') ?? 'ըստ ցանկության' }})' : '({{ __('tips.required') ?? 'պարտադիր' }})'"></span>
                        </div>
                        <textarea x-model="ratingComment" 
                                  rows="3" 
                                  maxlength="1000"
                                  placeholder="{{ __('tips.comment_placeholder') ?? 'Ի՞նչը կարող ենք բարելավել կամ ի՞նչն առանձնապես դուր եկավ...' }}"
                                  style="width: 100%; padding: 0.75rem 0.85rem; background: var(--bg-main); border: 1.5px solid var(--border-color); border-radius: 12px; color: var(--text-main); font-size: 0.88rem; outline: none; box-sizing: border-box; resize: vertical;"></textarea>
                    </div>

                    <!-- Anonymous Rating Checkbox -->
                    <template x-if="tipsRatingsSettings.allow_anonymous_ratings">
                        <label style="display: flex; align-items: center; gap: 0.6rem; cursor: pointer; font-size: 0.82rem; color: var(--text-main);">
                            <input type="checkbox" x-model="ratingIsAnonymous" style="width: 18px; height: 18px; accent-color: var(--primary); cursor: pointer;">
                            <span>{{ __('tips.submit_anonymously') ?? 'Ուղարկել անանուն (չհրապարակել իմ անունը)' }}</span>
                        </label>
                    </template>

                    <!-- Error Alert -->
                    <template x-if="ratingErrorMessage">
                        <div style="background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.3); border-radius: 10px; padding: 0.75rem 0.95rem; color: #ef4444; font-size: 0.82rem; display: flex; align-items: center; gap: 0.5rem;">
                            <i class="fa-solid fa-triangle-exclamation"></i>
                            <span x-text="ratingErrorMessage"></span>
                        </div>
                    </template>

                    <!-- Submit Button -->
                    <button type="button" 
                            @click="submitRatingForm()" 
                            :disabled="ratingSubmitting || (!tipsRatingsSettings.is_written_feedback_optional && (!ratingComment || !ratingComment.trim()))"
                            style="width: 100%; padding: 0.95rem; background: var(--primary); color: #fff; border: none; border-radius: 14px; font-weight: 800; font-size: 1rem; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 0.5rem; box-shadow: 0 4px 15px rgba(59, 130, 246, 0.35); transition: opacity 0.2s;"
                            :style="ratingSubmitting ? 'opacity: 0.6; cursor: wait;' : ''">
                        <template x-if="!ratingSubmitting">
                            <span style="display: flex; align-items: center; gap: 0.5rem;">
                                <i class="fa-solid fa-paper-plane"></i>
                                <span>{{ __('tips.send_feedback') ?? 'Ուղարկել կարծիքը' }}</span>
                            </span>
                        </template>
                        <template x-if="ratingSubmitting">
                            <span style="display: flex; align-items: center; gap: 0.5rem;">
                                <i class="fa-solid fa-spinner fa-spin"></i>
                                <span>{{ __('tips.sending') ?? 'Ուղարկվում է...' }}</span>
                            </span>
                        </template>
                    </button>
                </div>
            </template>
        </div>
    </div>
</div>
