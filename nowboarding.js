define(["dojo", "dojo/_base/declare", "ebg/core/gamegui", "ebg/counter"], function (dojo, declare) {
  return declare("bgagame.nowboarding", ebg.core.gamegui, {
    constructor() {},

    setup(gamedatas) {
      console.log("🐣 Setup", this.gamedatas);

      dojo.place(`<link rel="stylesheet" type="text/css" href="https://studio.boardgamearena.com:8084/data/themereleases/current/games/nowboarding/999999-9999/nowboarding.css?${Date.now()}"/>`, document.head, "last");

      // Setup map
      for (let nodeId in gamedatas.map.nodes) {
        var node = gamedatas.map.nodes[nodeId];
        dojo.place(`<div id="node-${nodeId}" class="marker node node-${nodeId}"></div>`, "NMap");
        if (node.weather) {
          this.createWeather(node);
        }
      }

      // Setup planes
      if (gamedatas.planes) {
        for (let planeId in gamedatas.planes) {
          let plane = gamedatas.planes[planeId];
          if (plane.location) {
            this.createPlane(plane);
          }
        }
      }

      // Setup pax

      // Setup player boards
      for (var player_id in gamedatas.players) {
        var player = gamedatas.players[player_id];
      }

      // Setup notifications
      dojo.subscribe("buildReset", this, "onNotify");
      dojo.subscribe("buy", this, "onNotify");
      dojo.subscribe("buyAlliance", this, "onNotify");
      dojo.subscribe("buyAlliancePrimary", this, "onNotify");
      dojo.subscribe("move", this, "onNotify");
      this.notifqueue.setSynchronous("move", 1000);
      dojo.subscribe("prepare", this, "onNotify");

      // Setup preferences
      this.setupPrefs();

      // Production bug report handler
      dojo.subscribe("loadBug", this, function loadBug(n) {
        function fetchNextUrl() {
          var url = n.args.urls.shift();
          console.log("Fetching URL", url);
          dojo.xhrGet({
            url: url,
            load: function (success) {
              console.log("Success for URL", url, success);
              if (n.args.urls.length > 0) {
                fetchNextUrl();
              } else {
                console.log("Done, reloading page");
                window.location.reload();
              }
            },
          });
        }
        console.log("Notif: load bug", n.args);
        fetchNextUrl();
      });

      // dojo.connect($("NMap"), "onclick", (e) => {
      //   var pos = dojo.position("NMap");
      //   var x = Math.round((e.offsetX / pos.w) * 1000) / 10 + "%";
      //   var y = Math.round((e.offsetY / pos.h) * 1000) / 10 + "%";
      //   dojo.style("testpoint", {
      //     left: x,
      //     top: y,
      //   });
      //   dojo.place("<b>left: " + x + "; top: " + y + ";</b>", "testview", "only");
      // });
    },

    setupPrefs() {
      // Extract the ID and value from the UI control
      var _this = this;
      function onchange(e) {
        var match = e.target.id.match(/^preference_[cf]ontrol_(\d+)$/);
        if (!match) {
          return;
        }
        var id = +match[1];
        var value = +e.target.value;
        _this.prefs[id].value = value;
        _this.onPrefChange(id, value);
      }

      // Call onPrefChange() when any value changes
      dojo.query(".preference_control").connect("onchange", onchange);

      // Call onPrefChange() now
      dojo.forEach(dojo.query("#ingame_menu_content .preference_control"), function (el) {
        onchange({ target: el });
      });
    },

    // ----------------------------------------------------------------------

    /* @Override */
    format_string_recursive: function (log, args) {
      if (log && args && !args.processed) {
        if (args.allianceFancy) {
          args.allianceFancy = `<span class="logfancy alliance alliance-${args.allianceFancy}"><i class="icon alliance ${args.allianceFancy}"></i> ${args.allianceFancy}</span>`;
        }
        if (args.seatFancy) {
          args.seatFancy = `<span class="logfancy seat"><i class="icon seat"></i> ${args.seatFancy}</span>`;
        }
        if (args.speedFancy) {
          args.speedFancy = `<span class="logfancy speed"><i class="icon speed"></i> ${args.speedFancy}</span>`;
        }
        args.processed = true;
      }
      return this.inherited(arguments);
    },

    // ----------------------------------------------------------------------

    // onEnteringState(stateName, args) {},

    // onLeavingState(stateName) {},

    onUpdateActionButtons(stateName, args) {
      console.log(`▶️ State ${stateName}`, args);

      if (!this.is_spectator) {
        if (this.isCurrentPlayerActive()) {
          if (args && args.buys && args.buys.length > 0) {
            let buys = args.buys;
            for (let i in buys) {
              let buy = buys[i];
              let id = "button_buy_" + buy.type;
              let txt = "";
              if (buy.type == "ALLIANCE") {
                id += "_" + buy.alliance;
                txt = `<div class="icon alliance ${buy.alliance}"></div>${buy.alliance} Alliance`;
              } else if (buy.type == "SEAT") {
                txt = `<div class="icon seat"></div>Seat ${buy.seat}`;
              } else if (buy.type == "SPEED") {
                txt = `<div class="icon speed"></div>Speed ${buy.speed}`;
              } else if (buy.type == "TEMP_SEAT") {
                txt = `<div class="icon seat"></div>Temporary Seat`;
              } else if (buy.type == "TEMP_SPEED") {
                txt = `<div class="icon speed"></div>Temporary Speed`;
              }
              if (buy.cost > 0) {
                txt += ` ($${buy.cost})`;
              }
              this.addActionButton(
                id,
                txt,
                function () {
                  this.takeAction("buy", buy);
                },
                null,
                false,
                "gray"
              );
            }
          }

          if (stateName == "preparePrivate") {
            this.addActionButton("button_flightBegin", _("Begin Round"), () => this.takeAction("flightBegin"));
          }

          if (stateName == "flyPrivate") {
            // Update the possible moves
            this.deleteMoves();
            if (args && args.moves) {
              let alliance = this.gamedatas.planes[this.player_id].alliance;
              let moveCount = 0;
              for (const m in args.moves) {
                const move = args.moves[m];
                const el = dojo.place(`<div id="move-${move.location}" class="move node node-${move.location} alliance-gradient-${alliance}">${move.fuel}</div>`, "NMap");
                if (el) {
                  moveCount++;
                  dojo.connect(el, "onclick", (e) => {
                    this.takeAction("move", move);
                  });
                }
              }
              console.log(`🗺️ Add ${moveCount} possible moves`);
            }

            this.addActionButton("button_flightEnd", _("End Round"), () => this.takeAction("flightEnd"), null, false, "red");
          }
        }

        if (stateName == "build" || stateName == "buildAlliance2" || stateName == "buildUpgrade") {
          this.addActionButton("button_buildReset", _("Start Over"), () => this.takeAction("buildReset"));
        }

        if (stateName == "fly" || stateName == "maintenance") {
          this.deleteMoves();
        }
      }
    },

    onNotify: function (notif) {
      console.log(`💬 Notify ${notif.type}`, notif.args);
      if (notif.type == "buildReset") {
        this.deletePlane(notif.args.plane);
      } else if (notif.type == "buyAlliancePrimary") {
        // Assign player color
        this.gamedatas.players[notif.args.player_id].color = notif.args.color;
        dojo.query(`#player_name_${notif.args.player_id} a`).style({
          color: `#${notif.args.color}`,
        });
        // Create plane
        this.createPlane(notif.args.plane);
      } else if (notif.type == "buyAlliance") {
        // TODO update player panel
      } else if (notif.type == "buy") {
        this.updatePlane(notif.args.plane);
      } else if (notif.type == "move") {
        if (notif.args.player_id == this.player_id) {
          // Erase possible moves
          this.deleteMoves();
        }
        this.rotatePlane(notif.args.plane);
        this.movePlane(notif.args.plane);
        this.updatePlane(notif.args.plane);
      } else if (notif.type == "prepare") {
        for (const plane of notif.args.planes) {
          this.updatePlane(plane);
        }
      }
    },

    onPrefChange: function (id, value) {
      console.log("Preference changed", id, value);
    },

    // ----------------------------------------------------------------------

    takeAction(action, data) {
      return new Promise((resolve, reject) => {
        // Prepare data
        data = data || {};
        data.version = this.gamedatas.version;
        if (data.lock === false) {
          delete data.lock;
        } else {
          data.lock = true;
          // if (this.isInterfaceLocked()) {
          //   throw `Take action ${action} ignored by interface lock`;
          // }
        }
        for (const key in data) {
          if (Array.isArray(data[key])) {
            data[key] = data[key].join(",");
          }
        }

        // Web call
        const start = Date.now();
        console.log(`👆 Take action ${action}`, data);
        this.ajaxcall(
          "/nowboarding/nowboarding/" + action + ".html",
          data,
          this,
          () => {},
          (error) => {
            const duration = Date.now() - start;
            if (error) {
              console.error(`Take action ${action} error in ${duration}ms`, lastErrorCode);
              reject(lastErrorCode);
            } else {
              console.log(`Take action ${action} done in ${duration}ms`);
              resolve();
            }
          }
        );
      });
    },

    createWeather(node) {
      var weatherIcon = node.weather == "FAST" ? "wind" : "storm";
      console.log(`🌦️ Create weather ${weatherIcon} at ${node.id}`);
      dojo.place(`<div id="weather-${node.id}" class="weather node node-${node.id}"><i class="icon weather ${node.weather}"></i></div>`, "NMap");
    },

    deleteWeather(node) {
      console.log(`❌ Delete weather`);
    },

    createPlane(plane) {
      console.log(`✈️ Create plane ${plane.id} (${plane.alliance}) at ${plane.location}`);
      dojo.place(
        `<div id="plane-${plane.id}" class="plane ${plane.alliance} node node-${plane.location}">
          <div class="planebody icon plane ${plane.alliance}"></div>
          <div class="planestats"></div>
        </div>`,
        "NMap"
      );
      if (plane.origin != null) {
        this.rotatePlane(plane);
      }
    },

    deletePlane(plane) {
      console.log(`❌ Delete plane ${plane.id}`);
      dojo.destroy("plane-" + plane.id);
    },

    movePlane(plane) {
      console.log(`✈️ Move plane ${plane.id} (${plane.alliance}) to ${plane.location}`);
      let el = document.getElementById(`plane-${plane.id}`);
      let classList = [...el.classList];
      classList.forEach((className) => {
        if (className.startsWith("node-")) {
          el.classList.remove(className);
        }
      });
      el.classList.add(`node-${plane.location}`);
    },

    rotatePlane(plane) {
      let locationPos = dojo.position(`node-${plane.location}`);
      let originPos = dojo.position(`node-${plane.origin}`);
      let rotation = (Math.atan2(locationPos.y - originPos.y, locationPos.x - originPos.x) * 180) / Math.PI + 90;
      // Normalize the rotation to between -180 +180
      if (rotation < -180) {
        console.warn(`rotation ${rotation} is too low`);
        rotation += 360;
      } else if (rotation > 180) {
        console.warn(`rotation ${rotation} is too high`);
        rotation -= 360;
      }

      console.log(`⤴️ Rotate plane ${plane.id} (${plane.alliance}) to ${rotation}deg`);
      dojo
        .query(`#plane-${plane.id} .planebody`)
        .at(0)
        .style({
          transform: `rotate(${rotation}deg)`,
        });
    },

    updatePlane(plane) {
      // console.log(`💵 Update plane ${plane.id} (${plane.alliance}) to ${plane.speedRemain} speed remaining, ${plane.seatRemain} seats remaining`);
      // dojo.query(`#plane-${plane.id} .planestats`).at(0).text(`${plane.speedRemain}/${plane.seatRemain}`);
    },

    deleteMoves() {
      console.log(`❌ Delete possible moves`);
      dojo.query("#NMap .move.node").forEach((el) => dojo.destroy(el));
    },
  });
});
