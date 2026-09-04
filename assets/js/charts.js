/*!
 * RideLog — charts
 *
 * Dependency-free SVG charting. Four shapes cover everything the dashboard
 * and the reports need: bar, line, donut and sparkline.
 *
 * Colours are read from CSS custom properties rather than hard-coded, so a
 * chart repaints correctly when the theme flips. Every chart also renders a
 * plain data table inside a <details>, which serves screen readers and prints
 * usefully when the SVG does not.
 *
 * Usage — put the data in a JSON script block and point an element at it:
 *
 *   <div data-chart="bar" data-chart-src="logs-by-month" data-chart-height="220"></div>
 *   <script type="application/json" id="logs-by-month">
 *     {"labels":["Jan","Feb"],"series":[{"name":"Logs","values":[4,7]}]}
 *   </script>
 */

(function (window, document) {
    'use strict';

    var RL = window.RL = window.RL || {};
    var SVG_NS = 'http://www.w3.org/2000/svg';

    /* ---------------------------------------------------------------------
       Helpers
       --------------------------------------------------------------------- */

    function svgEl(name, attrs) {
        var node = document.createElementNS(SVG_NS, name);

        Object.keys(attrs || {}).forEach(function (key) {
            if (attrs[key] !== null && attrs[key] !== undefined) {
                node.setAttribute(key, String(attrs[key]));
            }
        });

        return node;
    }

    function cssVar(name, fallback) {
        var value = getComputedStyle(document.documentElement).getPropertyValue(name);

        return value && value.trim() !== '' ? value.trim() : fallback;
    }

    /** The categorical palette, resolved against the current theme. */
    function palette() {
        return [
            cssVar('--brand-500', '#6366f1'),
            cssVar('--info-solid', '#2563eb'),
            cssVar('--ok-solid', '#16a34a'),
            cssVar('--warn-solid', '#f59e0b'),
            cssVar('--danger-solid', '#dc2626'),
            '#0891b2',
            '#7c3aed',
            '#db2777'
        ];
    }

    function prefersReducedMotion() {
        return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }

    /**
     * Choose axis ticks that land on round numbers.
     * Returns { max, step, ticks: [] }.
     */
    function niceScale(maxValue, targetTicks) {
        targetTicks = targetTicks || 5;

        if (!isFinite(maxValue) || maxValue <= 0) {
            return { max: 1, step: 1, ticks: [0, 1] };
        }

        var rawStep = maxValue / targetTicks;
        var magnitude = Math.pow(10, Math.floor(Math.log10(rawStep)));
        var normalised = rawStep / magnitude;
        var step;

        if (normalised <= 1) {
            step = 1;
        } else if (normalised <= 2) {
            step = 2;
        } else if (normalised <= 2.5) {
            step = 2.5;
        } else if (normalised <= 5) {
            step = 5;
        } else {
            step = 10;
        }

        step *= magnitude;

        var max = Math.ceil(maxValue / step) * step;
        var ticks = [];

        for (var value = 0; value <= max + step / 2; value += step) {
            ticks.push(Math.round(value * 1000) / 1000);
        }

        return { max: max, step: step, ticks: ticks };
    }

    function formatTick(value) {
        var abs = Math.abs(value);

        if (abs >= 1000000) {
            return (value / 1000000).toFixed(abs % 1000000 === 0 ? 0 : 1) + 'M';
        }

        if (abs >= 1000) {
            return (value / 1000).toFixed(abs % 1000 === 0 ? 0 : 1) + 'k';
        }

        return String(Math.round(value * 100) / 100);
    }

    /** Tooltip shared by every chart on the page. */
    function tooltipFor(container) {
        var tip = container.querySelector('.chart-tooltip');

        if (!tip) {
            tip = document.createElement('div');
            tip.className = 'chart-tooltip';
            tip.hidden = true;
            container.appendChild(tip);
        }

        return tip;
    }

    function showTip(container, text, x, y) {
        var tip = tooltipFor(container);
        tip.textContent = text;
        tip.hidden = false;
        tip.style.left = x + 'px';
        tip.style.top = y + 'px';
    }

    function hideTip(container) {
        var tip = container.querySelector('.chart-tooltip');

        if (tip) {
            tip.hidden = true;
        }
    }

    function emptyState(container, message) {
        container.innerHTML = '';
        var node = document.createElement('div');
        node.className = 'chart-empty';
        node.textContent = message || 'No data for this period.';
        container.appendChild(node);
    }

    /**
     * The accessible fallback: a real table of the same numbers, collapsed
     * into a <details> so it does not compete with the chart visually.
     */
    function dataTable(container, labels, series, formatter) {
        var details = document.createElement('details');
        details.className = 'chart-table';

        var summary = document.createElement('summary');
        summary.textContent = 'View as table';
        details.appendChild(summary);

        // The fallback table can be wider than a phone, so give it the same
        // horizontal scroll container every other table in the app uses.
        var wrap = document.createElement('div');
        wrap.className = 'table-wrap';

        var table = document.createElement('table');
        table.className = 'table table-compact';

        var thead = document.createElement('thead');
        var headRow = document.createElement('tr');
        headRow.appendChild(cell('th', ''));

        series.forEach(function (item) {
            headRow.appendChild(cell('th', item.name || 'Value'));
        });

        thead.appendChild(headRow);
        table.appendChild(thead);

        var tbody = document.createElement('tbody');

        labels.forEach(function (label, index) {
            var row = document.createElement('tr');
            row.appendChild(cell('th', label));

            series.forEach(function (item) {
                var value = item.values[index];
                row.appendChild(cell('td', formatter ? formatter(value) : String(value)));
            });

            tbody.appendChild(row);
        });

        table.appendChild(tbody);
        wrap.appendChild(table);
        details.appendChild(wrap);
        container.appendChild(details);

        function cell(tag, text) {
            var node = document.createElement(tag);
            node.textContent = text;

            return node;
        }
    }

    function legend(container, series, colors) {
        if (series.length < 2) {
            return;
        }

        var wrap = document.createElement('div');
        wrap.className = 'chart-legend';

        series.forEach(function (item, index) {
            var entry = document.createElement('span');
            entry.className = 'chart-legend-item';

            var swatch = document.createElement('span');
            swatch.className = 'chart-legend-swatch';
            swatch.style.background = colors[index % colors.length];

            var label = document.createElement('span');
            label.textContent = item.name || ('Series ' + (index + 1));

            entry.appendChild(swatch);
            entry.appendChild(label);
            wrap.appendChild(entry);
        });

        container.appendChild(wrap);
    }

    /* ---------------------------------------------------------------------
       Charts
       --------------------------------------------------------------------- */

    RL.chart = {};

    /**
     * Grouped vertical bar chart.
     *
     * data: { labels: [], series: [{ name, values: [] }] }
     */
    RL.chart.bar = function (container, data, options) {
        options = options || {};

        var labels = (data && data.labels) || [];
        var series = (data && data.series) || [];

        if (labels.length === 0 || series.length === 0) {
            emptyState(container, options.emptyText);

            return;
        }

        container.innerHTML = '';
        container.classList.add('chart');

        var width = Math.max(container.clientWidth || 640, 280);
        var height = options.height || 240;
        var padLeft = options.padLeft || 44;
        var padRight = 12;
        var padTop = 12;
        var padBottom = 34;

        var plotWidth = width - padLeft - padRight;
        var plotHeight = height - padTop - padBottom;

        var maxValue = 0;

        series.forEach(function (item) {
            item.values.forEach(function (value) {
                maxValue = Math.max(maxValue, parseFloat(value) || 0);
            });
        });

        var scale = niceScale(maxValue, 5);
        var colors = options.colors || palette();

        var svg = svgEl('svg', {
            viewBox: '0 0 ' + width + ' ' + height,
            role: 'img',
            'aria-label': options.title || 'Bar chart'
        });

        // Gridlines and y-axis labels
        var grid = svgEl('g', { class: 'chart-grid' });
        var axis = svgEl('g', { class: 'chart-axis' });

        scale.ticks.forEach(function (tick) {
            var y = padTop + plotHeight - (tick / scale.max) * plotHeight;

            grid.appendChild(svgEl('line', { x1: padLeft, y1: y, x2: width - padRight, y2: y }));

            var text = svgEl('text', { x: padLeft - 8, y: y + 4, 'text-anchor': 'end' });
            text.textContent = formatTick(tick);
            axis.appendChild(text);
        });

        svg.appendChild(grid);

        // Baseline
        axis.appendChild(svgEl('line', {
            x1: padLeft,
            y1: padTop + plotHeight,
            x2: width - padRight,
            y2: padTop + plotHeight
        }));

        var groupWidth = plotWidth / labels.length;
        var barGap = Math.min(6, groupWidth * 0.12);
        var barWidth = Math.max(2, (groupWidth - barGap * 2) / series.length);

        var bars = svgEl('g');

        labels.forEach(function (label, index) {
            var groupX = padLeft + index * groupWidth;

            series.forEach(function (item, seriesIndex) {
                var value = parseFloat(item.values[index]) || 0;
                var barHeight = scale.max === 0 ? 0 : (value / scale.max) * plotHeight;
                var x = groupX + barGap + seriesIndex * barWidth;
                var y = padTop + plotHeight - barHeight;

                var rect = svgEl('rect', {
                    class: 'chart-bar',
                    x: x,
                    y: prefersReducedMotion() ? y : padTop + plotHeight,
                    width: Math.max(1, barWidth - 1),
                    height: prefersReducedMotion() ? Math.max(0, barHeight) : 0,
                    rx: Math.min(3, barWidth / 3),
                    fill: colors[seriesIndex % colors.length]
                });

                rect.addEventListener('mouseenter', function () {
                    var rectBox = rect.getBoundingClientRect();
                    var containerBox = container.getBoundingClientRect();
                    var text = (series.length > 1 ? item.name + ': ' : '')
                        + (options.format ? options.format(value) : formatTick(value))
                        + ' — ' + label;

                    showTip(
                        container,
                        text,
                        rectBox.left - containerBox.left + rectBox.width / 2,
                        rectBox.top - containerBox.top
                    );
                });

                rect.addEventListener('mouseleave', function () { hideTip(container); });

                if (!prefersReducedMotion()) {
                    var animateY = svgEl('animate', {
                        attributeName: 'y',
                        from: padTop + plotHeight,
                        to: y,
                        dur: '0.45s',
                        fill: 'freeze',
                        begin: (index * 0.02) + 's'
                    });
                    var animateH = svgEl('animate', {
                        attributeName: 'height',
                        from: 0,
                        to: Math.max(0, barHeight),
                        dur: '0.45s',
                        fill: 'freeze',
                        begin: (index * 0.02) + 's'
                    });
                    rect.appendChild(animateY);
                    rect.appendChild(animateH);
                }

                bars.appendChild(rect);
            });

            // X label, thinned out when there is no room for all of them.
            var everyNth = Math.ceil(labels.length / Math.max(1, Math.floor(plotWidth / 46)));

            if (index % everyNth === 0) {
                var xText = svgEl('text', {
                    x: groupX + groupWidth / 2,
                    y: height - 12,
                    'text-anchor': 'middle'
                });
                xText.textContent = label;
                axis.appendChild(xText);
            }
        });

        svg.appendChild(bars);
        svg.appendChild(axis);
        container.appendChild(svg);

        legend(container, series, colors);
        dataTable(container, labels, series, options.format);
    };

    /**
     * Line chart, optionally area-filled.
     */
    RL.chart.line = function (container, data, options) {
        options = options || {};

        var labels = (data && data.labels) || [];
        var series = (data && data.series) || [];

        if (labels.length === 0 || series.length === 0) {
            emptyState(container, options.emptyText);

            return;
        }

        container.innerHTML = '';
        container.classList.add('chart');

        var width = Math.max(container.clientWidth || 640, 280);
        var height = options.height || 240;
        var padLeft = options.padLeft || 48;
        var padRight = 14;
        var padTop = 14;
        var padBottom = 34;

        var plotWidth = width - padLeft - padRight;
        var plotHeight = height - padTop - padBottom;

        var maxValue = 0;

        series.forEach(function (item) {
            item.values.forEach(function (value) {
                maxValue = Math.max(maxValue, parseFloat(value) || 0);
            });
        });

        var scale = niceScale(maxValue, 5);
        var colors = options.colors || palette();

        var svg = svgEl('svg', {
            viewBox: '0 0 ' + width + ' ' + height,
            role: 'img',
            'aria-label': options.title || 'Line chart'
        });

        var grid = svgEl('g', { class: 'chart-grid' });
        var axis = svgEl('g', { class: 'chart-axis' });

        scale.ticks.forEach(function (tick) {
            var y = padTop + plotHeight - (tick / scale.max) * plotHeight;
            grid.appendChild(svgEl('line', { x1: padLeft, y1: y, x2: width - padRight, y2: y }));

            var text = svgEl('text', { x: padLeft - 8, y: y + 4, 'text-anchor': 'end' });
            text.textContent = options.formatAxis ? options.formatAxis(tick) : formatTick(tick);
            axis.appendChild(text);
        });

        svg.appendChild(grid);

        var stepX = labels.length > 1 ? plotWidth / (labels.length - 1) : 0;

        function pointFor(value, index) {
            var x = padLeft + (labels.length > 1 ? index * stepX : plotWidth / 2);
            var y = padTop + plotHeight - ((parseFloat(value) || 0) / scale.max) * plotHeight;

            return { x: x, y: y };
        }

        series.forEach(function (item, seriesIndex) {
            var color = colors[seriesIndex % colors.length];
            var points = item.values.map(pointFor);
            var path = points.map(function (point, index) {
                return (index === 0 ? 'M' : 'L') + point.x.toFixed(1) + ' ' + point.y.toFixed(1);
            }).join(' ');

            if (options.area !== false && series.length === 1) {
                var areaPath = path
                    + ' L' + points[points.length - 1].x.toFixed(1) + ' ' + (padTop + plotHeight)
                    + ' L' + points[0].x.toFixed(1) + ' ' + (padTop + plotHeight) + ' Z';

                svg.appendChild(svgEl('path', { class: 'chart-area', d: areaPath, fill: color }));
            }

            var line = svgEl('path', { class: 'chart-line', d: path, stroke: color });

            if (!prefersReducedMotion()) {
                // Draw the line in by animating its dash offset.
                var length = Math.max(plotWidth, 1) * 1.6;
                line.setAttribute('stroke-dasharray', String(length));
                line.setAttribute('stroke-dashoffset', String(length));
                line.appendChild(svgEl('animate', {
                    attributeName: 'stroke-dashoffset',
                    from: length,
                    to: 0,
                    dur: '0.7s',
                    fill: 'freeze'
                }));
            }

            svg.appendChild(line);

            points.forEach(function (point, index) {
                var dot = svgEl('circle', {
                    class: 'chart-point',
                    cx: point.x,
                    cy: point.y,
                    r: points.length > 30 ? 2 : 3.5,
                    fill: color
                });

                var hit = svgEl('circle', {
                    cx: point.x,
                    cy: point.y,
                    r: 12,
                    fill: 'transparent',
                    style: 'cursor:pointer'
                });

                hit.addEventListener('mouseenter', function () {
                    var value = item.values[index];
                    var text = (series.length > 1 ? item.name + ': ' : '')
                        + (options.format ? options.format(value) : formatTick(value))
                        + ' — ' + labels[index];

                    showTip(container, text, point.x, point.y);
                    dot.setAttribute('r', '5');
                });

                hit.addEventListener('mouseleave', function () {
                    hideTip(container);
                    dot.setAttribute('r', String(points.length > 30 ? 2 : 3.5));
                });

                svg.appendChild(dot);
                svg.appendChild(hit);
            });
        });

        // X labels
        var everyNth = Math.ceil(labels.length / Math.max(1, Math.floor(plotWidth / 52)));

        labels.forEach(function (label, index) {
            if (index % everyNth !== 0 && index !== labels.length - 1) {
                return;
            }

            var x = padLeft + (labels.length > 1 ? index * stepX : plotWidth / 2);
            var text = svgEl('text', { x: x, y: height - 12, 'text-anchor': 'middle' });
            text.textContent = label;
            axis.appendChild(text);
        });

        axis.appendChild(svgEl('line', {
            x1: padLeft,
            y1: padTop + plotHeight,
            x2: width - padRight,
            y2: padTop + plotHeight
        }));

        svg.appendChild(axis);
        container.appendChild(svg);

        legend(container, series, colors);
        dataTable(container, labels, series, options.format);
    };

    /**
     * Donut chart.
     *
     * data: { slices: [{ label, value, color }] }
     */
    RL.chart.donut = function (container, data, options) {
        options = options || {};

        var slices = ((data && data.slices) || []).filter(function (slice) {
            return (parseFloat(slice.value) || 0) > 0;
        });

        if (slices.length === 0) {
            emptyState(container, options.emptyText);

            return;
        }

        container.innerHTML = '';
        container.classList.add('chart');

        var size = options.size || 200;
        var stroke = options.thickness || 26;
        var radius = (size - stroke) / 2;
        var centre = size / 2;
        var circumference = 2 * Math.PI * radius;

        var total = slices.reduce(function (sum, slice) {
            return sum + (parseFloat(slice.value) || 0);
        }, 0);

        var colors = options.colors || palette();

        var svg = svgEl('svg', {
            viewBox: '0 0 ' + size + ' ' + size,
            style: 'max-width:' + size + 'px;margin:0 auto',
            role: 'img',
            'aria-label': options.title || 'Donut chart'
        });

        // Track
        svg.appendChild(svgEl('circle', {
            cx: centre,
            cy: centre,
            r: radius,
            fill: 'none',
            stroke: cssVar('--muted-bg', '#eee'),
            'stroke-width': stroke
        }));

        var offset = 0;

        slices.forEach(function (slice, index) {
            var value = parseFloat(slice.value) || 0;
            var fraction = value / total;
            var length = fraction * circumference;
            var color = slice.color || colors[index % colors.length];

            var arc = svgEl('circle', {
                cx: centre,
                cy: centre,
                r: radius,
                fill: 'none',
                stroke: color,
                'stroke-width': stroke,
                'stroke-dasharray': length.toFixed(2) + ' ' + (circumference - length).toFixed(2),
                'stroke-dashoffset': (-offset).toFixed(2),
                transform: 'rotate(-90 ' + centre + ' ' + centre + ')',
                style: 'cursor:pointer;transition:stroke-width .12s'
            });

            arc.addEventListener('mouseenter', function () {
                arc.setAttribute('stroke-width', String(stroke + 5));

                var box = container.getBoundingClientRect();
                showTip(
                    container,
                    slice.label + ': ' + (options.format ? options.format(value) : formatTick(value))
                        + ' (' + Math.round(fraction * 100) + '%)',
                    box.width / 2,
                    box.height / 2
                );
            });

            arc.addEventListener('mouseleave', function () {
                arc.setAttribute('stroke-width', String(stroke));
                hideTip(container);
            });

            svg.appendChild(arc);
            offset += length;
        });

        // Centre label
        var centreValue = svgEl('text', {
            x: centre,
            y: centre - 2,
            'text-anchor': 'middle',
            style: 'font-size:26px;font-weight:700;fill:' + cssVar('--text', '#000')
        });
        centreValue.textContent = options.centreValue !== undefined
            ? String(options.centreValue)
            : formatTick(total);
        svg.appendChild(centreValue);

        var centreLabel = svgEl('text', {
            x: centre,
            y: centre + 18,
            'text-anchor': 'middle',
            style: 'font-size:12px;fill:' + cssVar('--text-muted', '#666')
        });
        centreLabel.textContent = options.centreLabel || 'Total';
        svg.appendChild(centreLabel);

        container.appendChild(svg);

        // Legend with values, which a donut badly needs
        var wrap = document.createElement('div');
        wrap.className = 'chart-legend';

        slices.forEach(function (slice, index) {
            var entry = document.createElement('span');
            entry.className = 'chart-legend-item';

            var swatch = document.createElement('span');
            swatch.className = 'chart-legend-swatch';
            swatch.style.background = slice.color || colors[index % colors.length];

            var label = document.createElement('span');
            label.textContent = slice.label + ' — ' + formatTick(parseFloat(slice.value) || 0);

            entry.appendChild(swatch);
            entry.appendChild(label);
            wrap.appendChild(entry);
        });

        container.appendChild(wrap);

        dataTable(
            container,
            slices.map(function (slice) { return slice.label; }),
            [{ name: options.valueLabel || 'Count', values: slices.map(function (slice) { return slice.value; }) }],
            options.format
        );
    };

    /** A tiny inline trend line, no axes. */
    RL.chart.sparkline = function (container, values, options) {
        options = options || {};

        var numbers = (values || []).map(function (value) { return parseFloat(value) || 0; });

        if (numbers.length < 2) {
            return;
        }

        container.innerHTML = '';

        var width = options.width || 100;
        var height = options.height || 28;
        var max = Math.max.apply(null, numbers);
        var min = Math.min.apply(null, numbers);
        var range = max - min || 1;
        var stepX = width / (numbers.length - 1);

        var path = numbers.map(function (value, index) {
            var x = index * stepX;
            var y = height - ((value - min) / range) * (height - 3) - 1.5;

            return (index === 0 ? 'M' : 'L') + x.toFixed(1) + ' ' + y.toFixed(1);
        }).join(' ');

        var svg = svgEl('svg', {
            viewBox: '0 0 ' + width + ' ' + height,
            width: width,
            height: height,
            'aria-hidden': 'true'
        });

        svg.appendChild(svgEl('path', {
            d: path,
            fill: 'none',
            stroke: options.color || cssVar('--brand-500', '#6366f1'),
            'stroke-width': 1.75,
            'stroke-linecap': 'round',
            'stroke-linejoin': 'round'
        }));

        container.appendChild(svg);
    };

    /* ---------------------------------------------------------------------
       Auto-initialisation
       --------------------------------------------------------------------- */

    function readData(element) {
        var sourceId = element.dataset.chartSrc;

        if (sourceId) {
            var node = document.getElementById(sourceId);

            if (node) {
                try {
                    return JSON.parse(node.textContent);
                } catch (e) {
                    return null;
                }
            }
        }

        if (element.dataset.chartData) {
            try {
                return JSON.parse(element.dataset.chartData);
            } catch (e) {
                return null;
            }
        }

        return null;
    }

    function renderOne(element) {
        var type = element.dataset.chart;
        var data = readData(element);

        if (!data) {
            emptyState(element, element.dataset.chartEmpty);

            return;
        }

        var options = {
            height: parseInt(element.dataset.chartHeight, 10) || undefined,
            size: parseInt(element.dataset.chartSize, 10) || undefined,
            title: element.dataset.chartTitle,
            emptyText: element.dataset.chartEmpty,
            centreLabel: element.dataset.chartCentreLabel,
            centreValue: element.dataset.chartCentreValue,
            area: element.dataset.chartArea !== '0'
        };

        if (element.dataset.chartFormat === 'money') {
            options.format = function (value) { return RL.fmt ? RL.fmt.money(value) : String(value); };
            options.formatAxis = function (value) {
                var symbol = (RL.config && RL.config.currency) || '$';

                return symbol + formatTick(value);
            };
        }

        try {
            if (type === 'bar') {
                RL.chart.bar(element, data, options);
            } else if (type === 'line') {
                RL.chart.line(element, data, options);
            } else if (type === 'donut') {
                RL.chart.donut(element, data, options);
            } else if (type === 'sparkline') {
                RL.chart.sparkline(element, data.values || data, options);
            }
        } catch (error) {
            emptyState(element, 'This chart could not be drawn.');
        }
    }

    function renderAll() {
        var elements = Array.prototype.slice.call(document.querySelectorAll('[data-chart]'));

        elements.forEach(renderOne);
    }

    RL.chart.renderAll = renderAll;
    RL.chart.render = renderOne;

    function boot() {
        renderAll();

        // Redraw on resize (debounced) and whenever the theme changes, since
        // the colours come from CSS custom properties.
        var redraw = RL.debounce ? RL.debounce(renderAll, 200) : renderAll;

        window.addEventListener('resize', redraw);
        window.addEventListener('rl:themechange', function () {
            window.setTimeout(renderAll, 40);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }

})(window, document);
