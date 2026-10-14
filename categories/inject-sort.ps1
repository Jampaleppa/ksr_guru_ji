$files = Get-ChildItem -Path "d:\KSR GURU JI\categories" -Filter "*.php"
$sortHtml = @"
    <div class="sort-container" style="margin-bottom: 20px; text-align: right;">
        <select id="sortSelect" style="padding: 8px 16px; border-radius: 60px; border: 1px solid var(--border-color, #eee); font-family: inherit; font-size: 0.9rem; font-weight: 600; color: var(--text-dark, #333); outline: none;">
            <option value="default">Sort By - Default</option>
            <option value="price_low">Price: Low to High</option>
            <option value="price_high">Price: High to Low</option>
        </select>
    </div>
"@

foreach ($file in $files) {
    if ($file.Name -in @('update-air-freshner.ps1')) { continue }
    $content = Get-Content -Raw -Encoding UTF8 $file.FullName
    if ($content -notmatch 'id="sortSelect"') {
        # Check if the file has search-bar end div
        $content = $content -replace '(?s)(<div class="search-bar">.*?</div>)(\s*)<div class="filter-row">', "`$1`$2$sortHtml`$2<div class=`"filter-row`">"
        Set-Content -Path $file.FullName -Value $content -Encoding UTF8
    }
}
