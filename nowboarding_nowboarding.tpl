{OVERALL_GAME_HEADER}

<script>
  document.querySelector('meta[name="viewport"]').content = 'width=900,interactive-widget=resizes-content';
</script>

<audio id="audiosrc_nowboarding_cash" src="{GAMETHEMEURL}img/cash.mp3" preload="none" autobuffer></audio>
<audio id="audiosrc_o_nowboarding_cash" src="{GAMETHEMEURL}img/cash.ogg" preload="none" autobuffer></audio>
<audio id="audiosrc_nowboarding_chime" src="{GAMETHEMEURL}img/chime.mp3" preload="none" autobuffer></audio>
<audio id="audiosrc_o_nowboarding_chime" src="{GAMETHEMEURL}img/chime.ogg" preload="none" autobuffer></audio>
<audio id="audiosrc_nowboarding_complaint" src="{GAMETHEMEURL}img/complaint.mp3" preload="none" autobuffer></audio>
<audio id="audiosrc_o_nowboarding_complaint" src="{GAMETHEMEURL}img/complaint.ogg" preload="none" autobuffer></audio>
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
    <div id="nbchatheader"><i class="fa fa-comment" aria-hidden="true"></i></div>
    <div id="nbchatscroll"></div>
  </div>
</div>

{OVERALL_GAME_FOOTER}