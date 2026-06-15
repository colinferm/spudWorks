'use strict';

const gulp       = require('gulp');
const sass       = require('gulp-sass')(require('sass'));
const sourcemaps = require('gulp-sourcemaps');

const THEME_DIR = '/var/www/html/wp-content/themes';

function compileSass() {
	return gulp
		.src(THEME_DIR + '/**/*.scss', { base: THEME_DIR })
		.pipe(sourcemaps.init())
		.pipe(sass({ outputStyle: 'expanded' }).on('error', sass.logError))
		.pipe(sourcemaps.write('.'))
		.pipe(gulp.dest(THEME_DIR));
}

function watchSass() {
	gulp.watch(THEME_DIR + '/**/*.scss', compileSass);
}

exports.build   = compileSass;
exports.watch   = gulp.series(compileSass, watchSass);
exports.default = exports.watch;
