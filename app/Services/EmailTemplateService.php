<?php

namespace App\Services;

use App\Models\EmailConfigurationAudit;
use App\Models\EmailTemplate;
use Illuminate\Support\Collection;

class EmailTemplateService
{
    /**
     * Retrieve all managed email templates.
     *
     * @return Collection<int, EmailTemplate>
     */
    public function getAllTemplates(): Collection
    {
        return EmailTemplate::orderBy('category')->orderBy('name')->get();
    }

    /**
     * Get a specific template by key.
     */
    public function getTemplate(string $key): ?EmailTemplate
    {
        return EmailTemplate::where('key', $key)->first();
    }

    /**
     * Render template content safely with placeholder variable interpolation.
     *
     * @param  array<string, mixed>  $variables
     * @return array{
     *     subject: string,
     *     intro: string,
     *     cta_text: ?string,
     *     cta_url: ?string,
     *     footer: ?string,
     *     support_contact: ?string,
     *     branding_logo_url: ?string,
     *     branding_primary_color: ?string
     * }|null
     */
    public function render(string $key, array $variables = [], ?string $locale = null): ?array
    {
        $template = $this->getTemplate($key);
        if (! $template || ! $template->is_active) {
            return null;
        }

        $subjectRaw = $template->getLocalizedField('subject', $locale);
        $introRaw = $template->getLocalizedField('intro_text', $locale);
        $ctaTextRaw = $template->getLocalizedField('cta_text', $locale);
        $ctaUrlRaw = $template->cta_url_template;
        $footerRaw = $template->getLocalizedField('footer_text', $locale);

        return [
            'subject' => $template->renderText($subjectRaw, $variables),
            'intro' => $template->renderText($introRaw, $variables),
            'cta_text' => filled($ctaTextRaw) ? $template->renderText($ctaTextRaw, $variables) : null,
            'cta_url' => filled($ctaUrlRaw) ? $template->renderText($ctaUrlRaw, $variables) : null,
            'footer' => filled($footerRaw) ? $template->renderText($footerRaw, $variables) : null,
            'support_contact' => $template->support_contact,
            'branding_logo_url' => $template->branding_logo_url,
            'branding_primary_color' => $template->branding_primary_color,
        ];
    }

    /**
     * Update editable template fields safely.
     *
     * @param  array<string, mixed>  $data
     */
    public function updateTemplate(EmailTemplate $template, array $data): EmailTemplate
    {
        $oldVersion = $template->version;

        $template->update([
            'subject' => $data['subject'] ?? $template->subject,
            'intro_text' => $data['intro_text'] ?? $template->intro_text,
            'cta_text' => $data['cta_text'] ?? $template->cta_text,
            'cta_url_template' => $data['cta_url_template'] ?? $template->cta_url_template,
            'footer_text' => $data['footer_text'] ?? $template->footer_text,
            'support_contact' => $data['support_contact'] ?? $template->support_contact,
            'branding_logo_url' => $data['branding_logo_url'] ?? $template->branding_logo_url,
            'branding_primary_color' => $data['branding_primary_color'] ?? $template->branding_primary_color,
            'is_active' => isset($data['is_active']) ? (bool) $data['is_active'] : $template->is_active,
            'translations' => $data['translations'] ?? $template->translations,
            'version' => $oldVersion + 1,
        ]);

        EmailConfigurationAudit::log(
            'template_updated',
            'EmailTemplate',
            $template->id,
            $template->name,
            ['key' => $template->key, 'new_version' => $template->version]
        );

        return $template;
    }
}
