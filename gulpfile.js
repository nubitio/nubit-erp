var gulp = require('gulp');
var less = require('gulp-less');
var LessAutoprefix = require('less-plugin-autoprefix');
var autoprefix = new LessAutoprefix({ browsers: ['last 2 versions'] });
var useref = require('gulp-useref');
var replace = require('gulp-replace');
var gulpif = require('gulp-if');
var phpMinify = require('gulp-php-minify');
var uglify = require('gulp-uglify');
var cssnano = require('gulp-cssnano');
var imagemin = require('gulp-imagemin');
var cache = require('gulp-cache');
var del = require('del');
var runSequence = require('run-sequence');

gulp.task('less', function() {
  return gulp.src('app/assets/less/**/*.less')
    .pipe(less({
      plugins: [autoprefix]
    }))
    .pipe(gulp.dest('app/assets/css'));
});

gulp.task('useref', function() {
  return gulp.src('app/views/**/*')
    .pipe(replace("{{@ASSETS}}", '../../assets/'))
    .pipe(useref())
    .pipe(gulpif('*.js', uglify()))
    .pipe(gulpif('*.css', cssnano()))
    .pipe(gulp.dest('dist/app/views'))
    .pipe(replace('../../assets/', "{{@ASSETS}}"))
    .pipe(replace('../assets/', "{{@ASSETS}}"))
    .pipe(gulp.dest('dist/app/views'));
});

gulp.task('images', function() {
  return gulp.src('app/assets/**/*.+(png|jpg|gif|svg)')
  .pipe(cache(imagemin({
    interlaced: true
  })))
  .pipe(gulp.dest('dist/app/assets'));
});

gulp.task('php', function() {
  return gulp.src(['**/*.php', '!node_modules/**', '!vendor/**', '!tmp/**'], {read:false})
  .pipe(phpMinify())
  .pipe(gulp.dest('dist'));
});

gulp.task('config', function() {
  return gulp.src([
    '**/*.ini', 
    '**/.htaccess', 
    'composer.json', 
    '!vendor/**',
    '!vendor/**/.*'
  ])
  .pipe(gulp.dest('dist'));
});

gulp.task('fonts', function() {
  return gulp.src('app/assets/fonts/**/*')
  .pipe(gulp.dest('dist/app/assets/fonts'));
});

// Cleaning 
gulp.task('clean:cache', function(callback) {
  return cache.clearAll(callback);
});

gulp.task('clean:dist', function() {
  return del.sync('dist');
});

// Watchers
gulp.task('watch', function() {
  gulp.watch('app/assets/less/**/*.less', ['less']);
});

// Sequences
gulp.task('default', function(callback) {
  runSequence(['less', 'watch'],
    callback
  );
});

gulp.task('build', function(callback) {
  runSequence(
    'clean:dist',
    'less',
    ['useref', 'images', 'php', 'config', 'fonts'],
    callback
  );
});