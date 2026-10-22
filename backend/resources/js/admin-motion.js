import gsap from 'gsap';
let media;
const animate = () => {
    media?.revert();
    media = gsap.matchMedia();
    media.add('(prefers-reduced-motion: no-preference)', () => {
        gsap.fromTo('[data-care-enter]', { y: 10, opacity: 0 }, { y: 0, opacity: 1, duration: 0.35, ease: 'power2.out', clearProps: 'transform,opacity', overwrite: 'auto' });
    });
};
document.addEventListener('livewire:navigating', () => media?.revert());
document.addEventListener('livewire:navigated', animate);
if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', animate, { once: true }); else animate();
