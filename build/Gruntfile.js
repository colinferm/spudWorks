/*global module:false*/
module.exports = function(grunt) {
	// Project configuration.
	grunt.initConfig({
		localconfig: grunt.file.readJSON('local.json'),
		sass: {
			dist: {
				options: {
					//style: 'compressed',
					style: 'expanded',
					unixNewlines: true
				},
				files: [{
					expand: true,
					cwd: '/var/www/html/wp-content/themes',
					src: ['**/*.scss'],
					dest: '/var/www/html/wp-content/themes',
					ext: '.css'
				}]
			}
		},
		watch: {
			scss: {
				files: ['/var/www/html/wp-content/themes/**/*.scss'],
				tasks: ['sass:dist'],
				interrupt: true
			}
		}
	});

	// These plugins provide necessary tasks.
	grunt.loadNpmTasks('grunt-contrib-concat');
	grunt.loadNpmTasks('grunt-contrib-uglify');
	grunt.loadNpmTasks('grunt-contrib-qunit');
	grunt.loadNpmTasks('grunt-contrib-jshint');
	grunt.loadNpmTasks('grunt-contrib-watch');
	grunt.loadNpmTasks('grunt-contrib-sass');
	grunt.loadNpmTasks('grunt-contrib-copy');
	grunt.loadNpmTasks('grunt-newer');
	grunt.loadNpmTasks('grunt-replace');

	// Default task.
	grunt.registerTask('default', ['watch']);
	grunt.registerTask('process-sass', ['sass:dist']);


};
