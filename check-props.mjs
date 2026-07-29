/**
 * Fails when a page passes a prop the design system does not declare.
 *
 * Vue silently renders an unknown prop as a plain HTML attribute on the
 * component's root element, so a typo binds nothing and still builds, still
 * passes every PHP test, and still renders — just without the value. Two such
 * bugs shipped during the Inertia migration: `DescriptionItem :value` (the
 * value belongs in the default slot) blanked the whole entry summary, and
 * `FormGrid :columns` (the prop is `cols`) silently left the form at its
 * two-column default.
 *
 * Props are read from the package's own `index.d.ts`, so this tracks the
 * installed version rather than a copy that would drift.
 */
import { readFileSync, readdirSync } from 'node:fs'
import { join, relative } from 'node:path'

const DTS = 'node_modules/@codebar-ag/storybook/dist/index.d.ts'
const PAGES = 'resources/js'

/** Attributes that are meaningful on any component. Everything else must be
 *  a declared prop, or fall through to a matching native element. */
const GLOBAL = new Set(['class', 'style', 'id', 'key', 'ref', 'slot'])

/** Components whose root is a native element the listed attributes belong to,
 *  where fallthrough is the intended behaviour rather than a mistake. */
const FALLTHROUGH = { Input: ['placeholder', 'autocomplete', 'autofocus', 'inputmode', 'min', 'max', 'step'] }

const dts = readFileSync(DTS, 'utf8')

const componentProps = new Map(
    [...dts.matchAll(/declare const (__VLS_component(?:_\d+)?): DefineComponent<(__VLS_Props(?:_\d+)?)\b/g)]
        .map((m) => [m[1], m[2]]),
)

const propsOf = (type) => {
    const m = dts.match(new RegExp(`declare type ${type} = \\{([\\s\\S]*?)\\n\\};`))
    if (!m) return null
    const body = m[1].replace(/\/\*[\s\S]*?\*\//g, '')
    return new Set([...body.matchAll(/^\s*([a-zA-Z]\w*)\??\s*:/gm)].map((x) => x[1]))
}

const components = new Map()
for (const m of dts.matchAll(/export declare const (\w+): __VLS_WithTemplateSlots(?:_\d+)?<typeof (__VLS_component(?:_\d+)?)/g)) {
    const type = componentProps.get(m[2])
    if (type) components.set(m[1], propsOf(type))
}
for (const m of dts.matchAll(/export declare const (\w+): DefineComponent<(__VLS_Props(?:_\d+)?)\b/g)) {
    components.set(m[1], propsOf(m[2]))
}

const camel = (n) => n.replace(/-([a-z])/g, (_, c) => c.toUpperCase())

/** Attribute names only — quoted values are blanked first so prose inside a
 *  `hint="…"` cannot be mistaken for a prop. */
const attributesIn = (tag) =>
    [...tag.replace(/=\s*"[^"]*"/g, '=""').replace(/=\s*'[^']*'/g, "=''")
        .matchAll(/(?:^|\s)(?::|v-bind:)?([a-zA-Z][\w-]*)(?=\s*=|\s|$)/g)]
        .map((m) => m[1])
        .filter((n) => !n.startsWith('v-') && !n.startsWith('@') && !n.startsWith('#'))
        .map(camel)

const vueFiles = readdirSync(PAGES, { recursive: true, encoding: 'utf8' })
    .filter((f) => f.endsWith('.vue'))
    .map((f) => join(PAGES, f))

let failures = 0
for (const file of vueFiles.sort()) {
    const src = readFileSync(file, 'utf8')
    const template = src.slice(src.indexOf('<template>'), src.lastIndexOf('</template>'))

    for (const m of template.matchAll(/<([A-Z]\w+)((?:[^>"']|"[^"]*"|'[^']*')*?)\/?>/g)) {
        const [, name, body] = m
        const declared = components.get(name)
        if (!declared) continue

        const allowed = new Set([...declared, ...GLOBAL, ...(FALLTHROUGH[name] ?? [])])
        for (const attribute of attributesIn(body)) {
            if (allowed.has(attribute)) continue
            const known = [...declared].sort().join(', ') || 'no props'
            console.error(`${relative('.', file)}: <${name}> got "${attribute}" — declares {${known}}`)
            failures++
        }
    }
}

console.log(
    failures === 0
        ? `check-props: ${vueFiles.length} files clean against ${components.size} components`
        : `check-props: ${failures} undeclared prop(s)`,
)
process.exit(failures === 0 ? 0 : 1)
