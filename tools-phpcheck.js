// Syntax-check every .php file under the plugin directory using glayzzle/php-parser.
const fs = require('fs');
const path = require('path');
const engine = require('php-parser');

const parser = new engine.Engine({
  parser: { php7: true, suppressErrors: false },
  ast: { withPositions: true },
});

const root = process.argv[2];
let failed = 0;
let checked = 0;

function walk(dir) {
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory()) walk(full);
    else if (entry.name.endsWith('.php')) {
      checked++;
      const code = fs.readFileSync(full, 'utf8');
      try {
        parser.parseCode(code, entry.name);
      } catch (e) {
        failed++;
        console.log('SYNTAX ERROR in ' + full + ': ' + e.message);
      }
    }
  }
}

walk(root);
console.log(checked + ' files checked, ' + failed + ' with syntax errors');
process.exit(failed ? 1 : 0);
