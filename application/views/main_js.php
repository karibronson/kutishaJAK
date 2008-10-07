/*
		* Main Javascript
		*/

		// Map JS
		jQuery(function() {
			var moved=false;
	
			// Now initialise the map
			var options = {
			units: "dd"
			, numZoomLevels: 16
			, controls:[]};
			var map = new OpenLayers.Map('map', options);
			map.addControl( new OpenLayers.Control.LoadingPanel({minSize: new OpenLayers.Size(573, 366)}) );
			
			var default_map = <?php echo $default_map; ?>;
			if (default_map == 2)
			{
				var map_layer = new OpenLayers.Layer.VirtualEarth("virtualearth");
			}
			else if (default_map == 3)
			{
				var map_layer = new OpenLayers.Layer.Yahoo("yahoo");
			}
			else if (default_map == 4)
			{
				var map_layer = new OpenLayers.Layer.OSM.Mapnik("openstreetmap");
			}
			else
			{
				var map_layer = new OpenLayers.Layer.Google("google");
			}
	
			map.addLayer(map_layer);
	
			map.addControl(new OpenLayers.Control.Navigation());
			map.addControl(new OpenLayers.Control.PanZoomBar());
			map.addControl(new OpenLayers.Control.Attribution());
			map.addControl(new OpenLayers.Control.MousePosition());
			map.addControl(new OpenLayers.Control.LayerSwitcher());
			
			
			var style = new OpenLayers.Style({
				pointRadius: "10",
				fillColor: "${color}",
				fillOpacity: 1,
				strokeColor: "#000000",
				strokeWidth: 1,
				strokeOpacity: 1
			}, 
			{
				context: 
				{
					color: function(feature) 
					{
						return "#" + feature.attributes.color;
					}
				}
			});
			
			var sliderfilter = new OpenLayers.Rule();
			style.addRules([sliderfilter]);
			
			// Create the markers layer
			var markers = new OpenLayers.Layer.GML("reports", "<?php echo url::base() . 'json' ?>", 
			{
				format: OpenLayers.Format.GeoJSON,
				projection: new OpenLayers.Projection("EPSG:4326"),
				styleMap: new OpenLayers.StyleMap({"default":style})
			});
			
			
			map.addLayer(markers);
			selectControl = new OpenLayers.Control.SelectFeature(markers,
                {onSelect: onFeatureSelect, onUnselect: onFeatureUnselect});

            map.addControl(selectControl);
            selectControl.activate();
			
			
			// create a lat/lon object
			var myPoint = new OpenLayers.LonLat(<?php echo $longitude; ?>, <?php echo $latitude; ?>);
	
			// display the map centered on a latitude and longitude (Google zoom levels)
			map.setCenter(myPoint, <?php echo $default_zoom; ?>);

			
			function onPopupClose(evt) {
	            selectControl.unselect(selectedFeature);
	        }
	        function onFeatureSelect(feature) {
	            selectedFeature = feature;
	            // Since KML is user-generated, do naive protection against
	            // Javascript.
	            var content = "<div class=\"infowindow\"><h2>"+feature.attributes.name + "</h2>" + feature.attributes.description + "</div>";
	            if (content.search("<script") != -1) {
	                content = "Content contained Javascript! Escaped content below.<br />" + content.replace(/</g, "&lt;");
	            }
	            popup = new OpenLayers.Popup.FramedCloud("chicken", 
	                                     feature.geometry.getBounds().getCenterLonLat(),
	                                     new OpenLayers.Size(100,100),
	                                     content,
	                                     null, true, onPopupClose);
	            feature.popup = popup;
	            map.addPopup(popup);
	        }
	        function onFeatureUnselect(feature) {
	            map.removePopup(feature.popup);
	            feature.popup.destroy();
	            feature.popup = null;
	        }
	
			// Category Switch
			$("a[@id^='cat_']").click(function() {
				var catID = this.id.substring(4);
				var catSet = 'cat_' + this.id.substring(4);
				$("a[@id^='cat_']").removeClass("active");
				$("#cat_" + catID).addClass("active");
				$("#currentCat").val(catID);
				markers.setUrl("<?php echo url::base() . 'json/index/' ?>" + catID);
			});
	
			//Accessible Slider/Select Switch
			$('select#startDate, select#endDate').accessibleUISlider({
				labels: 6,
				stop: function(e, ui) {
					var startDate = $("#startDate").val();
					var endDate = $("#endDate").val();
					var currentCat = $("#currentCat").val();

					var sliderfilter = new OpenLayers.Rule({
						filter: new OpenLayers.Filter.Comparison(
						{
				            type: OpenLayers.Filter.Comparison.BETWEEN,
				            property: "timestamp",
				            lowerBoundary: startDate,
							upperBoundary: endDate
				        })     
				    });
					style.addRules([sliderfilter]);
					markers.redraw();
					
					for (var i=0; i<style.rules.length; i++) {
						style.rules[i].destroy();
						style.rules[i] = null;
		 	        }
					style.rules = null;
					
					// refresh graph
					plotGraph();
				}
			});
			
			function setStyle(index) {
				var rule = new OpenLayers.rule({
					filter: new OpenLayers.Filter.Comparison({
						type: OpenLayers.Filter.Comparison.EQUAL_TO,
						property: "timestamp",
			            lowerBoundary: startDate,
						upperBoundary: endDate
					}),
					symbolizer: {
						Point: { fillColor:"FFFF00", strokeColor: "blue"}
					}
				});
	            gmlLayer.styleMap.styles["default"] = sld.namedLayers["markers"].userStyles[0].addRule(rule);  
	            gmlLayer.redraw();
	        }
			
			// Graph
			var allGraphData = [<?php echo $all_graphs ?>];
			var graphData = allGraphData[0]['ALL'];
			var graphOptions = {
				xaxis: { mode: "time", timeformat: "%b %y" },
				yaxis: { tickDecimals: 0 },
				points: { show: true},
				lines: { show: true}
			};

			function plotGraph() {	
				var startTime = new Date($("#startDate").val() * 1000);
				var endTime = new Date($("#endDate").val() * 1000);

				plot = $.plot($("#graph"), [graphData],
				        $.extend(true, {}, graphOptions, {
				            xaxis: { min: startTime.getTime(), max: endTime.getTime() }
				        }));
			}
			
			plotGraph();
			var categoryIds = [0,<?php echo join(array_keys($categories), ","); ?>]
				
			for (var i=0; i<categoryIds.length; i++) {
				$('#cat_'+categoryIds[i]).click(function(){
					var categories = <?php echo json_encode($categories); ?>;
					categories['0'] = ["ALL", "#0099CC"];
					graphData = allGraphData[0][categories[this.id.split("_")[1]][0]];
					plotGraph();
				});
			}
		});