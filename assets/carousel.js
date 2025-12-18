(function(){
  function initCarousel(root){
    if(!root) return;
    const track = root.querySelector('.jhh-carousel-track');
    const slides = Array.from(root.querySelectorAll('.jhh-slide'));
    const dotsWrap = root.querySelector('.jhh-carousel-dots');
    const prevBtn = root.querySelector('.jhh-carousel-prev');
    const nextBtn = root.querySelector('.jhh-carousel-next');

    let index = 0;
    const toBool = (v)=>/^(1|true|yes)$/i.test(String(v||''));
    const autoplay = toBool(root.dataset.autoplay);
    const intervalSec = Math.max(3, parseInt(root.dataset.interval ?? '7',10) || 7);
    const pauseHover = toBool(root.dataset.pauseHover);
    const showArrows = toBool(root.dataset.arrows);
    const showIndicators = toBool(root.dataset.indicators);

    if(!showArrows){ prevBtn.style.display='none'; nextBtn.style.display='none'; }
    if(!showIndicators){ dotsWrap.style.display='none'; }

    // Build dots
    const dots = [];
    if(showIndicators){
      slides.forEach((_,i)=>{
        const b = document.createElement('button');
        b.type = 'button';
        b.setAttribute('aria-label', 'Gehe zu Slide ' + (i+1));
        b.addEventListener('click', ()=>goTo(i));
        dotsWrap.appendChild(b);
        dots.push(b);
      });
    }

    function updateUI(){
      track.style.transform = 'translateX(' + (-index*100) + '%)';
      slides.forEach((s,i)=>s.setAttribute('aria-selected', i===index ? 'true' : 'false'));
      dots.forEach((d,i)=>d.setAttribute('aria-current', i===index ? 'true' : 'false'));
    }

    function goTo(i){ index = (i+slides.length)%slides.length; updateUI(); restart(); }
    function next(){ goTo(index+1); }
    function prev(){ goTo(index-1); }

    nextBtn.addEventListener('click', next);
    prevBtn.addEventListener('click', prev);

    // Swipe support
    let startX=0, touching=false;
    track.addEventListener('touchstart', (e)=>{ touching=true; startX=e.touches[0].clientX; if(pauseHover) pause(); }, {passive:true});
    track.addEventListener('touchmove', (e)=>{ if(!touching) return; const dx=e.touches[0].clientX-startX; if(Math.abs(dx)>60){ touching=false; if(dx<0) next(); else prev(); } }, {passive:true});
    track.addEventListener('touchend', ()=>{ touching=false; if(pauseHover) resume(); });

    if(pauseHover){
      root.addEventListener('mouseenter', pause);
      root.addEventListener('mouseleave', resume);
      root.addEventListener('focusin', pause);
      root.addEventListener('focusout', resume);
    }

    let timer=null;
  function start(){ if(autoplay && !timer){ timer = setInterval(next, intervalSec*1000); } }
    function pause(){ if(timer){ clearInterval(timer); timer=null; } }
    function resume(){ if(!timer){ start(); } }
    function restart(){ if(autoplay){ pause(); start(); } }

    // Keyboard
    root.addEventListener('keydown', (e)=>{
      if(e.key==='ArrowRight'){ e.preventDefault(); next(); }
      if(e.key==='ArrowLeft'){ e.preventDefault(); prev(); }
    });

    updateUI();
    // Start nach einem kurzen Tick, um Initial-Focus/Hover Effekte zu umgehen
    setTimeout(start, 120);

    // Browser-Tab Sichtbarkeit beachten
    document.addEventListener('visibilitychange', ()=>{
      if(document.hidden) pause(); else resume();
    });
  }

  document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('.jhh-carousel').forEach(initCarousel);
  });
})();
