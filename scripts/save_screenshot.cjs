const fs = require('fs');
const path = require('path');

function saveBase64Step(stepNum, filename) {
    const stepFile = path.join(
        'C:', 'Users', 'SOHAN HOSSAIN', '.gemini', 'antigravity-ide',
        'brain', '2235363e-6061-4b07-bf4c-b897579a5744',
        '.system_generated', 'steps', String(stepNum), 'output.txt'
    );

    if (!fs.existsSync(stepFile)) {
        console.error(`Step file not found: ${stepFile}`);
        return false;
    }

    const content = fs.readFileSync(stepFile, 'utf8');
    // Puppeteer output might be formatted like "data:image/png;base64,..." or JSON
    let base64 = content.trim();
    if (base64.includes('base64,')) {
        base64 = base64.split('base64,')[1];
    }
    // Clean any whitespace or quotes
    base64 = base64.replace(/[\r\n\s"']/g, '');

    const buffer = Buffer.from(base64, 'base64');
    
    // Save to brain screenshots
    const brainDir = path.join(
        'C:', 'Users', 'SOHAN HOSSAIN', '.gemini', 'antigravity-ide',
        'brain', '2235363e-6061-4b07-bf4c-b897579a5744', 'screenshots'
    );
    if (!fs.existsSync(brainDir)) fs.mkdirSync(brainDir, { recursive: true });
    
    const brainDest = path.join(brainDir, filename);
    fs.writeFileSync(brainDest, buffer);

    // Save to public screenshots
    const pubDir = path.join('d:', 'DhakaWeb', 'erp', 'public', 'screenshots');
    if (!fs.existsSync(pubDir)) fs.mkdirSync(pubDir, { recursive: true });

    const pubDest = path.join(pubDir, filename);
    fs.writeFileSync(pubDest, buffer);

    console.log(`Saved ${filename} (${buffer.length} bytes) to:\n  - ${brainDest}\n  - ${pubDest}`);
    return true;
}

const args = process.argv.slice(2);
if (args.length >= 2) {
    saveBase64Step(args[0], args[1]);
} else {
    console.log('Usage: node save_screenshot.cjs <stepNum> <filename.png>');
}
