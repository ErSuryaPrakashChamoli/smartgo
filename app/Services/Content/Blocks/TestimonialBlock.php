<?php

namespace App\Services\Content\Blocks;

use App\Models\Testimonial;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Select;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\Rule;

/**
 * Shows one testimonial from the testimonials list. It appears only while that testimonial is
 * published with consent on file and a verified statement (BR-CMS-7); quotes are never typed here.
 */
class TestimonialBlock implements ContentBlock
{
    public function type(): string
    {
        return 'testimonial';
    }

    public function label(): string
    {
        return 'Testimonial';
    }

    public function editorBlock(): Block
    {
        return Block::make($this->type())
            ->label($this->label())
            ->icon(Heroicon::OutlinedChatBubbleBottomCenterText)
            ->schema([
                Select::make('testimonial_id')
                    ->label('Testimonial')
                    ->options(fn (): array => Testimonial::query()->with('workingRevision')->get()
                        ->mapWithKeys(fn (Testimonial $testimonial): array => [$testimonial->getKey() => ($testimonial->person_name ?? $testimonial->workingRevision?->payload['person_name'] ?? 'Unnamed')
                            .($testimonial->isShowable() ? '' : ' (not published: hidden until it is)')])
                        ->all())
                    ->searchable()
                    ->markAsRequired()
                    ->helperText('Testimonials are added and approved under Website → Testimonials.'),
            ]);
    }

    public function rules(string $prefix, bool $forSubmission): array
    {
        return [
            $prefix => ['array:testimonial_id'],
            "{$prefix}.testimonial_id" => [$forSubmission ? 'required' : 'nullable', 'integer', Rule::exists('testimonials', 'id')],
        ];
    }

    public function normalize(array $data): array
    {
        return ['testimonial_id' => isset($data['testimonial_id']) ? (int) $data['testimonial_id'] : null];
    }

    public function present(array $data): array
    {
        $testimonial = isset($data['testimonial_id']) ? Testimonial::query()->find($data['testimonial_id']) : null;

        return [
            'testimonial' => $testimonial?->isShowable() ? [
                'quote' => (string) $testimonial->quote,
                'person' => (string) $testimonial->person_name,
                'role' => collect([$testimonial->person_title, $testimonial->company_name])->filter()->join(', '),
            ] : null,
        ];
    }

    public function component(): string
    {
        return 'blocks.testimonial';
    }
}
