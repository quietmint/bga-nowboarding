{OVERALL_GAME_HEADER}

<script>
  document.querySelector('meta[name="viewport"]').content = 'width=900,interactive-widget=resizes-content';
</script>

<audio id="audiosrc_nowboarding_cash" src="{GAMETHEMEURL}img/cash.mp3" preload="none" autobuffer></audio>
<audio id="audiosrc_o_nowboarding_cash" src="{GAMETHEMEURL}img/cash.ogg" preload="none" autobuffer></audio>
<audio id="audiosrc_nowboarding_chime" src="{GAMETHEMEURL}img/chime.mp3" preload="none" autobuffer></audio>
<audio id="audiosrc_o_nowboarding_chime" src="{GAMETHEMEURL}img/chime.ogg" preload="none" autobuffer></audio>
<audio id="audiosrc_nowboarding_complaint1" src="{GAMETHEMEURL}img/complaint1.mp3" preload="none" autobuffer></audio>
<audio id="audiosrc_o_nowboarding_complaint1" src="{GAMETHEMEURL}img/complaint1.ogg" preload="none" autobuffer></audio>
<audio id="audiosrc_nowboarding_complaint2" src="{GAMETHEMEURL}img/complaint2.mp3" preload="none" autobuffer></audio>
<audio id="audiosrc_o_nowboarding_complaint2" src="{GAMETHEMEURL}img/complaint2.ogg" preload="none" autobuffer></audio>
<audio id="audiosrc_nowboarding_complaint3" src="{GAMETHEMEURL}img/complaint3.mp3" preload="none" autobuffer></audio>
<audio id="audiosrc_o_nowboarding_complaint3" src="{GAMETHEMEURL}img/complaint3.ogg" preload="none" autobuffer></audio>
<audio id="audiosrc_nowboarding_complaint4" src="{GAMETHEMEURL}img/complaint4.mp3" preload="none" autobuffer></audio>
<audio id="audiosrc_o_nowboarding_complaint4" src="{GAMETHEMEURL}img/complaint4.ogg" preload="none" autobuffer></audio>
<audio id="audiosrc_nowboarding_plane" src="{GAMETHEMEURL}img/plane.mp3" preload="none" autobuffer></audio>
<audio id="audiosrc_o_nowboarding_plane" src="{GAMETHEMEURL}img/plane.ogg" preload="none" autobuffer></audio>
<audio id="audiosrc_nowboarding_walkway" src="{GAMETHEMEURL}img/walkway.mp3" preload="none" autobuffer></audio>
<audio id="audiosrc_o_nowboarding_walkway" src="{GAMETHEMEURL}img/walkway.ogg" preload="none" autobuffer></audio>

<div id="nbroot">
  <div id="nbscale">
    <div id="manifests-top" class="manifests"></div>
    <div id="nbmap" class="map">
      <div id="manifests-right" class="manifests"></div>
    </div>
    <div id="manifests-bottom" class="manifests"></div>
  </div>

  <div id="nbchat">
    <div id="nbchatheader">
      <i id="nbchathide" class="fa fa-minus-circle pull-right" aria-hidden="true"></i>
      <i class="fa fa-comment" aria-hidden="true"></i>
    </div>
    <div id="nbchatscroll"></div>
  </div>
</div>

{OVERALL_GAME_FOOTER}