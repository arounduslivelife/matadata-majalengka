const fs = require('fs');

function refactorJS() {
    let content = fs.readFileSync('index.php', 'utf8');
    
    // The main script starts with <script>\n    function openSidebar()
    // and ends before <!-- FINAL MODALS
    const jsRegex = /<script>\s*function openSidebar\(\) \{([\s\S]*?)<\/script>\s*(?=<.*FINAL MODALS)/;
    const match = content.match(jsRegex);

    if (match) {
        let jsContent = 'function openSidebar() {' + match[1];

        // Replace PHP tags with window.APP_DATA mapping
        jsContent = jsContent.replace(/const statsData = <\?= json_encode\(\$stats\) \?>;/g, 'const statsData = window.APP_DATA.stats;');
        jsContent = jsContent.replace(/const yearTotals = <\?= json_encode\(\$year_totals\) \?>;/g, 'const yearTotals = window.APP_DATA.year_totals;');
        jsContent = jsContent.replace(/const allAudits = <\?= json_encode\(\$all_audits\) \?>;/g, 'const allAudits = window.APP_DATA.all_audits;');
        jsContent = jsContent.replace(/const villageStats = <\?= json_encode\(\$village_stats\) \?>;/g, 'const villageStats = window.APP_DATA.village_stats;');
        jsContent = jsContent.replace(/const povertyStats = <\?= json_encode\(\$poverty_stats\) \?>;/g, 'const povertyStats = window.APP_DATA.poverty_stats;');
        jsContent = jsContent.replace(/const padData = <\?= \$pad_kecamatan_json \?>;/g, 'const padData = window.APP_DATA.pad_kecamatan;');
        jsContent = jsContent.replace(/const padGlobal = <\?= \$pad_global_json \?>;/g, 'const padGlobal = window.APP_DATA.pad_global;');
        
        jsContent = jsContent.replace(/<\?php if \(\$user\['gps_granted'\]\): \?>/g, 'if (window.APP_DATA.gps_granted) {');
        jsContent = jsContent.replace(/<\?php endif; \?>/g, '}');

        fs.writeFileSync('assets/js/main.js', jsContent.trim());

        const newScriptBlock = `
<script>
window.APP_DATA = {
    stats: <?= json_encode($stats) ?>,
    year_totals: <?= json_encode($year_totals) ?>,
    all_audits: <?= json_encode($all_audits) ?>,
    village_stats: <?= json_encode($village_stats) ?>,
    poverty_stats: <?= json_encode($poverty_stats) ?>,
    pad_kecamatan: <?= $pad_kecamatan_json ?>,
    pad_global: <?= $pad_global_json ?>,
    gps_granted: <?= $user['gps_granted'] ? 'true' : 'false' ?>
};
</script>
<script src="assets/js/main.js"></script>`;

        const newContent = content.replace(match[0], newScriptBlock + '\n');
        fs.writeFileSync('index.php', newContent);
        
        console.log('JS extracted successfully.');
    } else {
        console.log('Script block not found!');
    }
}

refactorJS();
