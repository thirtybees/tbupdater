module.exports = function(grunt) {

    grunt.initConfig({
        compress: {
            main: {
                options: {
                    archive: 'tbupdater.zip'
                },
                files: [
                    {src: ['classes/**'], dest: 'tbupdater/', filter: 'isFile'},
                    {src: ['controllers/**'], dest: 'tbupdater/', filter: 'isFile'},
                    {src: ['sql/**'], dest: 'tbupdater/', filter: 'isFile'},
                    {src: ['translations/**'], dest: 'tbupdater/', filter: 'isFile'},
                    {src: ['vendor/**'], dest: 'tbupdater/', filter: 'isFile'},
                    {src: ['views/**'], dest: 'tbupdater/', filter: 'isFile'},
                    {src: ['docs/**'], dest: 'tbupdater/', filter: 'isFile'},
                    {src: ['override/**'], dest: 'tbupdater/', filter: 'isFile'},
                    {src: ['logs/**'], dest: 'tbupdater/', filter: 'isFile'},
                    {src: ['upgrade/**'], dest: 'tbupdater/', filter: 'isFile'},
                    {src: ['optionaloverride/**'], dest: 'tbupdater/', filter: 'isFile'},
                    {src: ['oldoverride/**'], dest: 'tbupdater/', filter: 'isFile'},
                    {src: ['lib/**'], dest: 'tbupdater/', filter: 'isFile'},
                    {src: ['defaultoverride/**'], dest: 'tbupdater/', filter: 'isFile'},
                    {src: 'config.xml', dest: 'tbupdater/'},
                    {src: 'index.php', dest: 'tbupdater/'},
                    {src: 'tbupdater.php', dest: 'tbupdater/'},
                    {src: 'cloudunlock.php', dest: 'tbupdater/'},
                    {src: 'logo.png', dest: 'tbupdater/'},
                    {src: 'logo.gif', dest: 'tbupdater/'},
                    {src: 'LICENSE.md', dest: 'tbupdater/'},
                    {src: 'CONTRIBUTORS.md', dest: 'tbupdater/'},
                    {src: 'README.md', dest: 'tbupdater/'}
                ]
            }
        }
    });

    grunt.loadNpmTasks('grunt-contrib-compress');

    grunt.registerTask('default', ['compress']);
};
