<?php

namespace Tests\Feature\Frontend;

use PHPUnit\Framework\TestCase;

/**
 * Source-level guards for the sidebar mascot and the Inter preload (no database, no browser). The real-browser checks are
 * tests/Support/mascot-browser.cjs and tests/Support/typography-browser.cjs. The mascot is self-contained: it needs no global
 * motion tokens, no view transitions and no change to scrolling, so these tests also pin that independence.
 */
final class MascotBrandTest extends TestCase
{
    private function read(string $path): string
    {
        return file_get_contents(dirname(__DIR__, 3).'/'.$path);
    }

    public function test_the_mascot_is_self_contained_and_adds_no_global_motion(): void
    {
        $css = $this->read('public/css/mascot.css');
        $this->assertStringContainsString('--rbm-duration: 180ms', $css, 'its own 180ms pose transition');
        $this->assertStringNotContainsString('--rb-duration', $css);
        $this->assertStringNotContainsString('--rb-ease', $css);
        $this->assertStringNotContainsString('view-transition', $css);
        $this->assertStringContainsString('prefers-reduced-motion: reduce', $css);
        $layout = $this->read('resources/views/layouts/app.blade.php');
        $this->assertStringNotContainsString('motion.css', $layout, 'the global motion layer is not part of this change');
        $this->assertFileDoesNotExist(dirname(__DIR__, 3).'/public/css/foundations/motion.css');
        $this->assertStringNotContainsString('view-transition', $this->read('public/css/layout.css').$this->read('public/css/foundations/components.css'));
        $this->assertStringNotContainsString('scroll-behavior', $this->read('public/css/layout.css'), 'smooth scrolling is not part of this change');
        $this->assertStringNotContainsString('scrollend', $this->read('public/js/layout.js'));
        $this->assertStringNotContainsString('indicator', $this->read('resources/views/components/ui/nav-item.blade.php'));
        $this->assertStringContainsString('css/mascot.css', $layout);
        $this->assertStringContainsString('js/mascot.js', $layout);
    }

    public function test_the_college_admin_sidebar_gets_a_pose_for_its_single_link(): void
    {
        // College admin has one link (icon "accounts"); it is mapped like every other icon, so it is the badge pose.
        $layout = $this->read('resources/views/layouts/app.blade.php');
        $this->assertStringContainsString("'icon' => 'accounts', 'href' => route('college.home')", $layout);
        $this->assertStringContainsString("'accounts' => 'badge'", $this->read('resources/views/layouts/partials/navigation.blade.php'));
    }

    public function test_every_inter_weight_the_shell_uses_is_preloaded_once_as_woff2(): void
    {
        // font-display: optional drops any face that is late for the whole page view, so a weight that is used above the fold but
        // not preloaded (the brand subtitle is Inter Medium) can render in the fallback while the Bold heading is Inter.
        $layout = $this->read('resources/views/layouts/app.blade.php');
        $this->assertStringContainsString("@foreach (['Regular', 'Medium', 'SemiBold', 'Bold'] as \$weight)", $layout);
        $this->assertStringContainsString('<link rel="preload" href="{{ asset(\'fonts/inter/Inter-\'.$weight.\'.woff2\') }}" as="font" type="font/woff2" crossorigin>', $layout);
        $this->assertSame(1, substr_count($layout, 'rel="preload"'), 'one preload loop, no duplicate links');
        foreach (['Regular', 'Medium', 'SemiBold', 'Bold'] as $weight) {
            $this->assertFileExists(dirname(__DIR__, 3)."/public/fonts/inter/Inter-$weight.woff2");
        }
        $fonts = $this->read('public/css/foundations/fonts.css');
        $this->assertSame(4, substr_count($fonts, 'font-display: optional'), 'font-display stays optional (no late layout shift)');
        $this->assertStringNotContainsString('font-display: swap', $fonts);
    }


    public function test_the_mascot_component_is_decorative_inert_and_has_no_text_or_controls(): void
    {
        $svg = $this->read('resources/views/components/ui/mascot.blade.php');
        $markup = preg_replace('/\{\{--.*?--\}\}/s', '', $svg);
        $this->assertSame(2, substr_count($markup, 'aria-hidden="true"'), 'the wrapper and the svg are both hidden from assistive technology');
        $this->assertStringContainsString('focusable="false"', $markup);
        foreach (['<a ', '<a>', '<button', '<input', '<select', 'tabindex', 'role=', '<title', '<desc', '<text', 'href=', 'onclick', 'aria-live', 'aria-label', '<script', '<image', '<foreignObject', '<linearGradient', '<radialGradient', '<filter'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $markup, "the mascot must not contain $forbidden");
        }
        $this->assertStringContainsString("@props(['pose' => 'idle'])", $markup, 'rest pose defaults to idle');
        $this->assertStringContainsString('data-pose="{{ $pose }}"', $markup, 'the server renders the current page\'s pose');
        $this->assertStringNotContainsString('http', $markup, 'no external asset or namespace dependency');
        $this->assertLessThan(10 * 1024, strlen($svg), 'a small asset');
        $this->assertFileExists(dirname(__DIR__, 3).'/docs/frontend/ReserveBot-Mascot.jpg', 'the design reference lives beside the docs');
    }


    public function test_every_pose_has_a_prop_and_a_stylesheet_rule_and_the_geometry_is_shared(): void
    {
        $svg = $this->read('resources/views/components/ui/mascot.blade.php');
        $css = $this->read('public/css/mascot.css');
        foreach (['door', 'calendar', 'clipboard', 'key', 'tool', 'badge'] as $prop) {
            $this->assertStringContainsString("rbm-prop--$prop\"", $svg, "$prop prop is drawn");
            $this->assertStringContainsString("[data-pose=\"$prop\"] .rbm-prop--$prop", $css, "$prop prop appears only in its pose");
        }
        foreach (['attend', 'wave', 'door'] as $pose) {
            $this->assertStringContainsString("data-pose=\"$pose\"", $css, "$pose pose parameters");
        }
        // One holding system: two-handed poses (calendar, clipboard, door share one footprint) angle both arms inward onto the side
        // edges; one-handed poses (key, tool, badge) share one LEFT carrier hand; the greeting poses raise the right arm / open both.
        $this->assertStringContainsString('.rb-mascot:is([data-pose="calendar"], [data-pose="clipboard"], [data-pose="door"]) { --rbm-l: -23.4deg; --rbm-r: 23.4deg; }', $css);
        $this->assertStringContainsString('.rb-mascot:is([data-pose="key"], [data-pose="tool"], [data-pose="badge"]) { --rbm-l: -28.4deg; }', $css);
        $this->assertStringContainsString('.rb-mascot[data-pose="wave"] { --rbm-r: -150deg;', $css);
        $this->assertStringContainsString('.rb-mascot[data-pose="attend"] { --rbm-l: 100deg; --rbm-r: -100deg;', $css);
        $this->assertStringContainsString('--rbm-l: 20deg; --rbm-r: -20deg;', $css, 'idle: relaxed arms');
        $this->assertStringContainsString('.rbm-arm--l :is(.rbm-hand, .rbm-hand-edge, .rbm-limb-edge)', $css, 'one-handed poses show only the carrier hand');
        // One body, built once: head-and-body silhouette, faceplate, legs, antenna, chest pill and two eyes, however many poses.
        $this->assertSame(1, substr_count($svg, 'class="rbm-maroon" x="26" y="40" width="88" height="83" rx="25"'), 'one rounded head/body silhouette');
        $this->assertSame(1, substr_count($svg, 'class="rbm-cream" x="37" y="54" width="66" height="39" rx="17"'), 'one cream faceplate');
        $this->assertDoesNotMatchRegularExpression('/\\.rbm-pill[^{]*\\{[^}]*opacity:\\s*0/', $css, 'the chest pill is never hidden by a pose; only a held object may cover it');
        $this->assertDoesNotMatchRegularExpression('/\\.rbm-pill[^{]*\\{[^}]*transition/', $css, 'and it never fades');
        $this->assertLessThan(strpos($svg, 'class="rbm-arm rbm-arm--l"'), strpos($svg, 'rbm-pill'), 'the pill is body: drawn before the limbs, props and hands');
        preg_match('/rbm-prop--tool\">(.*?)<\\/g>\\s*<\\/g>/s', $svg, $tool);
        $this->assertNotEmpty($tool);
        $this->assertStringNotContainsString('rbm-maroon', $tool[1], 'the wrench is one light colour: no maroon patch painted over its head');
        $this->assertStringContainsString('class="rbm-wrench" d="M-3.2 -17.875A8.5 8.5 0 1 0 3.2 -17.875V-12H-3.2Z"', $tool[1], 'the open jaw is a real gap in the head path');
        $this->assertStringContainsString('.rbm-wrench { fill: var(--rb-color-cream); stroke: var(--rb-color-cream)', $css);
        $this->assertSame(2, substr_count($svg, 'width="16" height="26" rx="8"'), 'one pair of stubby legs');
        $this->assertSame(2, substr_count($svg, 'cy="69" r="5"'), 'two simple round eyes');
        $this->assertSame(1, substr_count($svg, 'class="rbm-gold rbm-pill"'), 'one gold chest accent');
        $this->assertSame(1, substr_count($svg, 'class="rbm-aerial"'), 'one short antenna');
        $this->assertStringNotContainsString('>RB<', $svg, 'the pose sheet RB belly logo is not part of the character');
        $this->assertStringContainsString('rbm-eyes--happy', $svg, 'happy eyes for the greeting poses');
        $this->assertStringNotContainsString('hand-ball', $svg, 'the old gold wave hand is gone: the raised arm ends in the shared hand');
        $this->assertSame(4, substr_count($svg, 'class="rbm-arm '), 'two limbs and two hands, all posed by the same two angle variables');
        $this->assertSame(2, substr_count($svg, 'rbm-arm rbm-arm--l rbm-hands') + substr_count($svg, 'rbm-arm rbm-arm--r rbm-hands'), 'each hand turns with its own arm');
        $this->assertSame(2, substr_count($svg, 'class="rbm-hand" '), 'one hand per arm, the same size');
        $this->assertGreaterThan(strpos($svg, 'rbm-prop--tool'), strpos($svg, 'rbm-hands'), 'hands are drawn after every prop, so they sit on the object');
        $this->assertLessThan(strpos($svg, 'class="rbm-cream" x="37"'), strpos($svg, 'rbm-limb"'), 'limbs are drawn under the faceplate, so an arm never cuts into the face');
        $this->assertSame(6, substr_count($svg, 'class="rbm-prop rbm-prop--'), 'six props and nothing else is duplicated per pose');
        // The character is centred on x=100 of a 200-wide artwork (translate 30 + centre 70), so centring the figure centres the robot.
        $this->assertStringContainsString('viewBox="0 6 200 140"', $svg);
        $this->assertStringContainsString('translate(30 0)', $svg);
        $this->assertStringContainsString('x="26" y="40" width="88"', $svg, 'silhouette spans 26..114, centred on 70');
        // Flat vector only: the palette comes from the shared tokens (no hard-coded hex), no gradients or shadows.
        $this->assertDoesNotMatchRegularExpression('/#[0-9a-fA-F]{3,8}\b/', $css, 'mascot colours are --rb-color-* tokens');
        $this->assertDoesNotMatchRegularExpression('/#[0-9a-fA-F]{3,8}\b/', preg_replace('/\{\{--.*?--\}\}/s', '', $svg));
        $this->assertStringNotContainsString('gradient', $css);
        $this->assertStringNotContainsString('box-shadow', $css);
    }


    public function test_pose_mapping_lives_in_the_shared_navigation_and_covers_every_icon_with_a_fallback(): void
    {
        $nav = $this->read('resources/views/layouts/partials/navigation.blade.php');
        preg_match("/\\\$poses = \\[(.*?)\\];.*?\\\$poseOf = fn \\(\\\$item\\) => \\\$poses\\[\\\$item\\['icon'\\]\\] \\?\\? '(\w+)'/s", $nav, $found);
        $this->assertNotEmpty($found, 'the mapping is declared once, in navigation.blade.php');
        preg_match_all("/'(\w+)' => '(\w+)'/", $found[1], $pairs, PREG_SET_ORDER);
        $map = array_column($pairs, 2, 1);
        $this->assertSame(['home' => 'wave', 'rooms' => 'door', 'book' => 'calendar', 'bookings' => 'clipboard', 'today' => 'calendar',
            'requests' => 'clipboard', 'manage' => 'key', 'equipment' => 'tool', 'accounts' => 'badge'], $map);
        $this->assertSame('attend', $found[2], 'links without a designed pose get a neutral gesture');
        $this->assertStringContainsString("if (\$item['active'])", $nav, 'the page starts from the active link\'s pose (child pages inherit via the existing active state)');
        $this->assertStringContainsString("\$pagePose = 'idle'", $nav, 'a page with no active link shows the plain idle figure');
        $this->assertStringContainsString('<x-ui.mascot :pose="$pagePose" />', $nav);
        $item = $this->read('resources/views/components/ui/nav-item.blade.php');
        $this->assertStringContainsString('data-rb-pose="{{ $pose }}"', $item);
        $this->assertStringNotContainsString("'home' => 'wave'", $item, 'the mapping is not duplicated in the nav item');
        $this->assertDoesNotMatchRegularExpression("/['\"](wave|door|clipboard|key|tool|badge)['\"]/", $this->read('public/js/mascot.js').$this->read('public/js/layout.js'), 'no route or pose is hard-coded in JavaScript');

        // Every icon the shell can use for a link is mapped, so no role has an unposed link.
        $layout = $this->read('resources/views/layouts/app.blade.php');
        preg_match_all("/'icon' => '(\w+)'/", $layout, $icons);
        foreach (array_unique($icons[1]) as $icon) {
            $this->assertArrayHasKey($icon, $map, "icon $icon has a pose");
        }
        $css = $this->read('public/css/mascot.css');
        foreach (array_unique([...array_values($map), $found[2]]) as $pose) {
            $this->assertStringContainsString("[data-pose=\"$pose\"]", $css, "$pose is styled");
        }
        // The mascot is included by the shared sidebar only, once, and no new link was added.
        $this->assertSame(1, substr_count($nav, '<x-ui.mascot'));
        $this->assertStringContainsString('class="rb-navigation"', $nav);
        $this->assertStringNotContainsString('Signed in as', $nav);
        foreach (['resources/views/rooms', 'resources/views/home', 'resources/views/bookings', 'resources/views/requests', 'resources/views/manage'] as $dir) {
            foreach (glob(dirname(__DIR__, 3).'/'.$dir.'/*.blade.php') as $file) {
                $this->assertStringNotContainsString('x-ui.mascot', file_get_contents($file), 'the mascot is never duplicated into pages: '.basename($file));
            }
        }
    }


    public function test_mascot_footer_rules_hide_it_instead_of_squeezing_the_navigation(): void
    {
        $css = $this->read('public/css/mascot.css');
        $this->assertMatchesRegularExpression('/\.rb-mascot\s*\{\s*display:\s*none;\s*\}/', $css, 'hidden by default (phones, compact layout, no container support)');
        $this->assertStringContainsString('@media (min-width: 80rem) and (min-height: 39.5rem)', $css, 'same desktop breakpoint as the stationary sidebar in layout.css');
        $this->assertMatchesRegularExpression('/flex:\s*1 1 0;\s*min-height:\s*0;\s*margin-top:\s*calc\(-1 \* var\(--rb-space-8\)\)/', $css, 'the slot is only leftover space and cancels the sidebar gap');
        $this->assertStringContainsString('container: rb-mascot / size', $css);
        $this->assertMatchesRegularExpression('/@container rb-mascot \(min-height: \d+(\.\d+)?rem\)\s*\{\s*\.rb-mascot__stage\s*\{\s*display:\s*grid;/', $css, 'the footer is shown only when the leftover space can hold it');
        $this->assertStringContainsString('justify-items: center', $css, 'centred in the sidebar content area');
        $this->assertStringContainsString('border-top: var(--rb-border-width) solid var(--rb-border-warm)', $css, 'a hairline separates the footer from the navigation');
        $this->assertStringContainsString('pointer-events: none', $css);
        preg_match('/@container rb-mascot \(min-height: ([\d.]+)rem\)/', $css, $threshold);
        preg_match('/\.rb-mascot__art\s*\{[^}]*height:\s*([\d.]+)rem/', $css, $height);
        $this->assertGreaterThanOrEqual(5.5, (float) $height[1]);   // ~88px
        $this->assertLessThanOrEqual(5.75, (float) $height[1]);     // ~92px
        $this->assertGreaterThan((float) $height[1] + 1, (float) $threshold[1], 'room for the separator and padding as well as the figure');
        // It never styles navigation links, so it cannot move them; the layout file is not touched by it.
        $this->assertStringNotContainsString('.rb-nav-item', $css);
        $this->assertStringNotContainsString('.rb-navigation', $css);
        $this->assertStringNotContainsString('mascot', $this->read('public/css/layout.css'));
        $this->assertStringNotContainsString('role="tab"', $css);
        $this->assertStringNotContainsString('max-height', $css);
    }


    public function test_mascot_script_keeps_the_pose_until_the_next_page_and_has_no_side_effects(): void
    {
        $js = $this->read('public/js/mascot.js');
        $this->assertStringContainsString("document.getElementById('rb-navigation')", $js);
        $this->assertSame(4, preg_match_all('/addEventListener\(/', $js), 'delegated pointerover, pointermove and focusin on the sidebar, plus pageshow');
        foreach (["'pointerover'", "'pointermove'", "'focusin'", "'pageshow'"] as $event) {
            $this->assertStringContainsString($event, $js);
        }
        foreach (["'pointerout'", "'focusout'", "'mouseleave'", "'blur'", "'click'", 'setTimeout', 'GRACE_MS'] as $absent) {
            $this->assertStringNotContainsString($absent, $js, "the pose never resets on $absent: it stays until the next page load");
        }
        $this->assertStringContainsString(':focus-visible', $js, 'only keyboard focus counts, not a mouse click');
        $this->assertStringContainsString("pointerType === 'touch'", $js);
        $this->assertStringContainsString('event.movementX || event.movementY', $js, 'a parked pointer after a page load must not preview another link');
        $this->assertStringContainsString("pagePose = mascot.dataset.pose", $js, 'starts from the server-rendered page pose');
        $this->assertStringContainsString('mascot.dataset.pose === pose', $js, 'no DOM write when nothing changes');
        $this->assertStringContainsString("mascot.dataset.ready = ''", $js);
        $this->assertStringNotContainsString('requestAnimationFrame', $js, 'nothing is armed on arrival: the wave only plays on a preview');
        foreach (['fetch(', 'XMLHttpRequest', 'sendBeacon', 'localStorage', 'sessionStorage', 'document.cookie', 'indexedDB', "'mousemove'", 'setInterval', 'preventDefault', 'location', '.focus(', 'aria-live', 'innerHTML'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $js, "mascot.js must not use $forbidden");
        }
        // Without script the server-rendered page pose is all there is.
        $this->assertStringContainsString('data-pose="{{ $pose }}"', $this->read('resources/views/components/ui/mascot.blade.php'));
    }
}
