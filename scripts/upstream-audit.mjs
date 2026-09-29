#!/usr/bin/env node
import { createHash } from 'node:crypto'
import { readFileSync, existsSync } from 'node:fs'
import { resolve } from 'node:path'

const root = resolve(import.meta.dirname, '..')
const manifest = JSON.parse(readFileSync(resolve(root, 'docs/upstream-foundation.json'), 'utf8'))
const hash = (path) => createHash('sha256').update(readFileSync(path)).digest('hex')
const modified = Object.entries(manifest.protected).filter(([path, expected]) => {
    const file = resolve(root, path)
    return !existsSync(file) || hash(file) !== expected
})
if (modified.length) {
    console.error('Protected CarpDraw presentation changed:\n' + modified.map(([path]) => path).join('\n'))
    process.exitCode = 1
} else {
    console.log(`${Object.keys(manifest.protected).length} protected CarpDraw files unchanged from ${manifest.carpdraw_base}.`)
}
const sourceIndex = process.argv.indexOf('--source')
if (sourceIndex >= 0) {
    const source = process.argv[sourceIndex + 1]
    if (!source) throw new Error('--source requires an upstream checkout path')
    const drift = Object.entries(manifest.imports).filter(([path, record]) => {
        const file = resolve(source, record.source_path ?? path)
        return !existsSync(file) || hash(file) !== record.source_sha256
    })
    if (drift.length) {
        console.error('Upstream changes needing review:\n' + drift.map(([path]) => path).join('\n'))
        process.exitCode = 1
    } else {
        console.log(`${Object.keys(manifest.imports).length} upstream sources match the reviewed ${manifest.upstream_commit} snapshot.`)
    }
}
