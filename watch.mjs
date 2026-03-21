import fs from 'fs';
import path from 'path';
import * as sass from 'sass';

/**
 * Walk the theme.config.php inheritance chain (mixins + extends) and return
 * the list of include paths that sass needs for the given compiled.scss file.
 * Ported from getLoadPaths() in Gruntfile.js.
 */
function getLoadPaths(file) {
  const parts = file.split('/');
  parts.pop(); // eliminate filename

  // initialize search path with directory containing the SCSS file
  const retVal = [];
  retVal.push(parts.join('/'));
  retVal.push(parts.join('/') + '/vendor/');

  const themeBase = parts.slice(0, -1);
  retVal.push(themeBase.join('/') + '/node_modules/');

  // Iterate through theme.config.php files collecting parent themes in search path:
  let config;
  while (config = fs.readFileSync('themes/' + parts[1] + '/theme.config.php', 'UTF-8')) {
    // First identify mixins:
    const mixinMatches = config.match(/["']mixins["']\s*=>\s*\[([^\]]+)\]/);
    if (mixinMatches !== null) {
      const mixinParts = mixinMatches[1].split(',');
      for (let i = 0; i < mixinParts.length; i++) {
        parts[1] = mixinParts[i].trim().replace(/['"]/g, '');
        retVal.push(parts.join('/') + '/');
      }
    }

    // Now move up to parent theme:
    const matches = config.match(/["']extends["']\s*=>\s*['"]([\w\-]+)['"]/);

    // "extends" set to "false" or missing entirely? We've hit the end of the line:
    if (matches === null || matches[1] === 'false') {
      break;
    }

    parts[1] = matches[1];
    retVal.push(parts.join('/') + '/');
    retVal.push(parts.join('/') + '/vendor/');

    const parentThemeBase = parts.slice(0, -1);
    retVal.push(parentThemeBase.join('/') + '/node_modules/');
  }
  return retVal;
}

/**
 * Discover all themes that have a compiled.scss and return an array of
 * { theme, src, dest, includePaths } objects.
 */
function discoverThemes() {
  return fs.readdirSync(path.resolve('themes'))
    .filter(theme => fs.existsSync(path.resolve('themes/' + theme + '/scss/compiled.scss')))
    .map(theme => {
      const src = path.join('themes', theme, 'scss', 'compiled.scss');
      const dest = path.join('themes', theme, 'css', 'compiled.css');
      const includePaths = getLoadPaths('themes/' + theme + '/scss/compiled.scss');
      includePaths.push('vendor/');
      return { theme, src, dest, includePaths };
    });
}

/**
 * Compile a single theme's scss to css using the sass JS API.
 */
function compileTheme(themeConfig) {
  const { theme, src, dest, includePaths } = themeConfig;
  try {
    const result = sass.compile(src, {
      style: 'compressed',
      quietDeps: true,
      loadPaths: includePaths
    });
    fs.mkdirSync(path.dirname(dest), { recursive: true });
    fs.writeFileSync(dest, result.css);
    console.log(`[watch.mjs] Compiled ${theme}: ${src} -> ${dest}`);
  } catch (err) {
    console.error(`[watch.mjs] Error compiling ${theme}: ${err.message}`);
  }
}

/**
 * Compile all discovered themes.
 */
function compileAll(themes) {
  for (const themeConfig of themes) {
    compileTheme(themeConfig);
  }
}

// Discover themes once at startup
const themes = discoverThemes();
if (themes.length === 0) {
  console.error('[watch.mjs] No themes with scss/compiled.scss found. Exiting.');
  process.exit(1);
}

// Initial compile (atBegin: true behaviour)
console.log('[watch.mjs] Starting initial compile...');
compileAll(themes);
console.log('[watch.mjs] Watching for changes in themes/*/scss/ ...');

// Watch all theme scss directories for changes
const watchers = themes.map(themeConfig => {
  const watchDir = path.join('themes', themeConfig.theme, 'scss');
  return fs.watch(watchDir, { recursive: true }, (eventType, filename) => {
    if (filename && filename.endsWith('.scss')) {
      console.log(`[watch.mjs] Change detected in ${watchDir}/${filename}, recompiling all themes...`);
      compileAll(themes);
    }
  });
});

// Exit cleanly on SIGINT (Ctrl-C)
process.on('SIGINT', () => {
  console.log('\n[watch.mjs] Stopping watchers...');
  for (const watcher of watchers) {
    watcher.close();
  }
  process.exit(0);
});
