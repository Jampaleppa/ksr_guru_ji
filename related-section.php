<?php
// includes/related-section.php
$base_url = $base_url ?? '../';
$currentCategory = $currentCategory ?? 'all';

// Top generic categories for quick navigation links
$relatedLinks = [
    'stationery' => ['link' => 'stationery.php', 'label' => 'Stationery', 'icon' => 'fa-pen'],
    'personal-care' => ['link' => 'personal-care.php', 'label' => 'Personal Care', 'icon' => 'fa-hand-holding-heart'],
    'vegetables-fruits' => ['link' => 'vegetables-fruits.php', 'label' => 'Vegetables', 'icon' => 'fa-carrot'],
    'bakery' => ['link' => 'bakery.php', 'label' => 'Bakery', 'icon' => 'fa-bread-slice'],
    'cleaning' => ['link' => 'cleaning.php', 'label' => 'Cleaning', 'icon' => 'fa-broom'],
    'dry-fruits' => ['link' => 'dry-fruits.php', 'label' => 'Dry Fruits', 'icon' => 'fa-seedling'],
    'snacks' => ['link' => 'snacks.php', 'label' => 'Snacks', 'icon' => 'fa-cookie'],
    'spices' => ['link' => 'spices.php', 'label' => 'Spices', 'icon' => 'fa-pepper-hot']
];

$quotes = [
    "\"Let food be thy medicine and medicine be thy food.\" — Hippocrates",
    "\"Take care of your body. It's the only place you have to live.\" — Jim Rohn",
    "\"The greatest wealth is health.\" — Virgil",
    "\"To ensure good health: eat lightly, breathe deeply, live moderately, cultivate cheerfulness, and maintain an interest in life.\" — William Londen"
];
$randomQuote = $quotes[array_rand($quotes)];
?>

<div class="related-section" style="margin-top: 40px; border-top: 1px solid #f2e5d9; padding-top: 30px;">
    <h3 style="font-size: 1.5rem; color: #3e2c1b; margin-bottom: 20px; font-weight: 700;">Explore More / ಇನ್ನಷ್ಟು ಅನ್ವೇಷಿಸಿ</h3>
    <div style="display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 30px;">
        <?php foreach ($relatedLinks as $key => $data): ?>
            <?php if ($key !== $currentCategory): ?>
                <a href="<?php echo $base_url . 'categories/' . $data['link']; ?>" style="background: #fdf8f4; border: 1px solid #f2e5d9; padding: 12px 20px; border-radius: 40px; text-decoration: none; color: #5c3e24; font-weight: 600; font-size: 0.9rem; transition: background 0.2s;">
                    <i class="fas <?php echo $data['icon']; ?>" style="color: #b45f2b; margin-right: 8px;"></i> <?php echo $data['label']; ?>
                </a>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    
    <div style="background: #fdfaf7; padding: 24px; border-radius: 20px; text-align: center; border: 1px dashed #e6d9cd;">
        <p style="font-style: italic; color: #7b5f45; font-size: 1.05rem; margin: 0; line-height: 1.6;">
            <?php echo $randomQuote; ?>
        </p>
    </div>
</div>
