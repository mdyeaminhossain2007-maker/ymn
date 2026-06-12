<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Ad extends Model
{
    protected string $table = 'ads';

    /** @return array<int, array<string, mixed>> */
    public function activeFor(string $placement): array
    {
        return $this->db()->all(
            "SELECT * FROM ads
             WHERE placement = ? AND status = 1
               AND (starts_at IS NULL OR starts_at <= NOW())
               AND (ends_at IS NULL OR ends_at >= NOW())
             ORDER BY id DESC",
            [$placement]
        );
    }

    public function render(string $placement): string
    {
        $ads = $this->activeFor($placement);
        if (!$ads) {
            return '';
        }
        $html = '';
        foreach ($ads as $ad) {
            $this->db()->run('UPDATE ads SET impressions = impressions + 1 WHERE id = ?', [$ad['id']]);
            if ($ad['type'] === 'image' && $ad['image']) {
                $img = '<img src="' . e($ad['image']) . '" alt="' . e($ad['title']) . '" class="img-fluid">';
                $html .= $ad['link']
                    ? '<a href="' . e($ad['link']) . '" target="_blank" rel="nofollow noopener">' . $img . '</a>'
                    : $img;
            } else {
                // html or script content (admin-controlled)
                $html .= $ad['content'] ?? '';
            }
        }
        return '<div class="stv-ad stv-ad-' . e($placement) . '">' . $html . '</div>';
    }
}
