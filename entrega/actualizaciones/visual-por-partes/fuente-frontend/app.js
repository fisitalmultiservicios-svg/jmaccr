// Progressive enhancements are introduced with the CMS milestone.
const menuToggle=document.querySelector('[data-menu-toggle]');
menuToggle?.addEventListener('click',()=>{const expanded=menuToggle.getAttribute('aria-expanded')==='true';menuToggle.setAttribute('aria-expanded',String(!expanded));document.getElementById('site-nav')?.classList.toggle('is-open',!expanded);});
import './admin.js';
document.querySelectorAll('.cms-slider').forEach(slider=>{const slides=[...slider.querySelectorAll('.slide')];let index=0;const move=direction=>{index=(index+direction+slides.length)%slides.length;slides.forEach((slide,i)=>slide.hidden=i!==index);slider.querySelector('[data-slide-count]').textContent=`${index+1} / ${slides.length}`;};slider.querySelector('[data-slide-prev]')?.addEventListener('click',()=>move(-1));slider.querySelector('[data-slide-next]')?.addEventListener('click',()=>move(1));});
import './cart.js';
import './requests.js';
