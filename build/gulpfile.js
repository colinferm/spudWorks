'use strict';

const path              = require('path');
const { fileURLToPath } = require('url');
const { Transform }     = require('stream');
const gulp              = require('gulp');
const sass              = require('sass');
const sourcemaps        = require('gulp-sourcemaps');
const PluginError       = require('plugin-error');
const replaceExtension  = require('replace-ext');
const applySourceMap    = require('vinyl-sourcemaps-apply');

const THEME_DIR  = '/var/www/html/wp-content/themes';
const PLUGIN_NAME = 'spudmag-sass';

/**
 * Compiles each non-partial .scss file with Dart Sass's modern compileAsync()
 * API. gulp-sass only ever calls the legacy render()/renderSync() API, which
 * Dart Sass is removing (see https://sass-lang.com/d/legacy-js-api), so this
 * replaces it directly rather than relying on that plugin.
 */
function compileScss() {
	return new Transform({
		objectMode: true,
		transform(file, encoding, callback) {
			if (file.isNull() || path.basename(file.path).startsWith('_')) {
				callback();
				return;
			}

			const wantsSourceMap = Boolean(file.sourceMap);
			const options = {
				style: 'expanded',
				loadPaths: [path.dirname(file.path)],
				sourceMap: wantsSourceMap,
				sourceMapIncludeSources: wantsSourceMap
			};

			sass.compileAsync(file.path, options).then((result) => {
				if (wantsSourceMap) {
					const map = result.sourceMap;
					map.file = replaceExtension(file.relative, '.css');
					// Dart Sass reports sources as absolute file:// URLs; gulp-sourcemaps
					// expects paths relative to the compiled file.
					map.sources = map.sources.map((source) => (
						path.relative(path.dirname(file.path), fileURLToPath(source))
					));
					applySourceMap(file, map);
				}
				file.contents = Buffer.from(result.css);
				file.path = replaceExtension(file.path, '.css');
				callback(null, file);
			}).catch((error) => {
				callback(new PluginError(PLUGIN_NAME, error, { showStack: false }));
			});
		}
	});
}

function logSassError(error) {
	process.stderr.write(new PluginError(PLUGIN_NAME, error, { showStack: false }).toString() + '\n');
	this.emit('end');
}

function compileSass() {
	return gulp
		.src(THEME_DIR + '/**/*.scss', { base: THEME_DIR })
		.pipe(sourcemaps.init())
		.pipe(compileScss().on('error', logSassError))
		.pipe(sourcemaps.write('.'))
		.pipe(gulp.dest(THEME_DIR));
}

function watchSass() {
	gulp.watch(THEME_DIR + '/**/*.scss', compileSass);
}

exports.build   = compileSass;
exports.watch   = gulp.series(compileSass, watchSass);
exports.default = exports.watch;
