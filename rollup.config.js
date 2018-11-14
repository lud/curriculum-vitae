import buble from 'rollup-plugin-buble'
import commonjs from 'rollup-plugin-commonjs'
import path from 'path'
import postcss from 'rollup-plugin-postcss'
import resolve from 'rollup-plugin-node-resolve'
import {
  uglify
} from 'rollup-plugin-uglify'

// const production = !process.env.ROLLUP_WATCH
const production = true


function createConfig(config) {

  const baseConfig = {
    plugins: [
      resolve({
        extensions: ['.html', '.js', '.scss', '.sass'],
        module: true,
        main: true,
        browser: true
      }),
      postcss({
        extract: true,
        minimize: production
      }),
      // If you have external dependencies installed from
      // npm, you'll most likely need these plugins. In
      // some cases you'll need additional configuration —
      // consult the documentation for details:
      // https://github.com/rollup/rollup-plugin-commonjs
      commonjs(),
      // If we're building for production (npm run build
      // instead of npm run dev), transpile and minify
      production && buble({
        include: ['assets/**']
      }),
      production && uglify(),
    ]
  }

  return Object.assign(baseConfig, config)
}

export default [
  createConfig({
    input: 'src/js/main.js',
    output: {
      sourcemap: true,
      format: 'iife',
      name: 'cv', // export in global namespace
      file: production ?
        'assets/bundle.min.js' : 'assets/bundle.js'
    }
  }),
]
