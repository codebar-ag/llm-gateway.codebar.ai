<div class="flex-1 p-4 max-lg:hidden">
    <div
        class="text-white relative rounded-lg h-full w-full bg-zinc-900 overflow-hidden flex flex-col items-start justify-end p-16">
        <div class="absolute inset-0 bg-cover bg-center"
            style="background-image: url('{{ config('auth.hero.lq_url') }}')"></div>
        <div class="absolute inset-0 bg-cover bg-center opacity-0 transition-opacity duration-300"
            style="background-image: url('{{ config('auth.hero.hq_url') }}')" x-data="authHeroHq(@js(config('auth.hero.hq_url')))"
            :class="loaded ? 'opacity-100' : 'opacity-0'"></div>
    </div>
</div>
