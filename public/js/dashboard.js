(function (window) {
    'use strict';

    var COLORS = ['#3d8bfd', '#e35d6a', '#3fb950', '#d4a017', '#a371f7'];
    var MUTED = '#8b9aab';
    var GRID = 'rgba(42, 53, 66, 0.8)';

    function formatNumber(value) {
        return Number(value || 0).toLocaleString('pt-BR');
    }

    function formatPercent(value) {
        return Number(value || 0).toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }) + '%';
    }

    function axisDefaults() {
        return {
            ticks: { color: MUTED },
            grid: { color: GRID }
        };
    }

    function Dashboard(root, initialMetrics) {
        this.root = root;
        this.view = root.dataset.view || 'consolidated';
        this.charts = {};
        this.bind();
        this.render(initialMetrics);
        this.connectEcho();
    }

    Dashboard.prototype.bind = function () {
        var self = this;
        var modal = document.getElementById('insight-modal');

        this.root.querySelectorAll('[data-view-btn]').forEach(function (button) {
            button.addEventListener('click', function () {
                self.setView(button.getAttribute('data-view-btn'));
            });
        });

        this.root.querySelector('#insight-btn').addEventListener('click', function () {
            self.openInsightModal();
        });

        if (modal) {
            modal.querySelectorAll('[data-close-modal]').forEach(function (el) {
                el.addEventListener('click', function () {
                    self.closeInsightModal();
                });
            });
        }

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                self.closeInsightModal();
            }
        });
    };

    Dashboard.prototype.openInsightModal = function () {
        var modal = document.getElementById('insight-modal');
        if (!modal) {
            return;
        }
        modal.hidden = false;
        document.body.style.overflow = 'hidden';
        this.fetchInsight();
    };

    Dashboard.prototype.closeInsightModal = function () {
        var modal = document.getElementById('insight-modal');
        if (!modal || modal.hidden) {
            return;
        }
        modal.hidden = true;
        document.body.style.overflow = '';
    };

    Dashboard.prototype.fetchInsight = function () {
        var output = document.getElementById('insight-output');
        var button = this.root.querySelector('#insight-btn');
        var token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        button.disabled = true;
        output.textContent = 'Gerando insight...';

        fetch('/api/insights', {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': token,
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ view: this.view })
        })
            .then(function (response) {
                return response.json().then(function (body) {
                    return { ok: response.ok, body: body };
                });
            })
            .then(function (result) {
                if (!result.ok) {
                    throw new Error(result.body.message || 'Erro ao gerar insight');
                }
                output.textContent = result.body.insight;
            })
            .catch(function (error) {
                output.textContent = error.message;
            })
            .finally(function () {
                button.disabled = false;
            });
    };

    Dashboard.prototype.setView = function (view) {
        this.view = view;
        this.root.dataset.view = view;

        this.root.querySelectorAll('[data-view-btn]').forEach(function (button) {
            button.classList.toggle('is-active', button.getAttribute('data-view-btn') === view);
        });

        this.fetchMetrics();
    };

    Dashboard.prototype.fetchMetrics = function () {
        var self = this;

        return fetch('/api/metrics?view=' + encodeURIComponent(this.view), {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then(function (response) {
                if (!response.ok) {
                    throw new Error('Falha ao carregar métricas');
                }
                return response.json();
            })
            .then(function (data) {
                self.render(data);
            })
            .catch(function (error) {
                console.error(error);
            });
    };

    Dashboard.prototype.render = function (metrics) {
        this.root.querySelector('#view-label').textContent = metrics.label + ' · por produto';
        this.root.querySelector('#live-date').textContent = metrics.date;
        this.root.querySelector('#updated-at').textContent = 'Atualizado ' + metrics.updated_at;
        this.root.querySelector('#kpi-produced').textContent = formatNumber(metrics.totals.produced_qty);
        this.root.querySelector('#kpi-defective').textContent = formatNumber(metrics.totals.defective_qty);
        this.root.querySelector('#kpi-defect-rate').textContent = formatPercent(metrics.totals.defect_rate);
        this.root.querySelector('#kpi-efficiency').textContent = formatPercent(metrics.totals.efficiency);
        this.root.querySelector('#kpi-defect-card').classList.toggle('is-alert', !!metrics.totals.alert);
        this.root.querySelector('#alert-banner').classList.toggle('is-visible', !!metrics.has_alerts);

        var tbody = this.root.querySelector('#products-body');
        tbody.innerHTML = (metrics.products || []).map(function (row) {
            return '<tr class="' + (row.alert ? 'is-alert' : '') + '">' +
                '<td>' + row.product_name + '</td>' +
                '<td>' + formatNumber(row.produced_qty) + '</td>' +
                '<td>' + formatNumber(row.defective_qty) + '</td>' +
                '<td>' + formatPercent(row.defect_rate) + '</td>' +
                '<td>' + formatPercent(row.efficiency) + '</td>' +
                '</tr>';
        }).join('');

        this.renderCharts(metrics);
    };

    Dashboard.prototype.upsertChart = function (key, canvasId, config) {
        var canvas = this.root.querySelector(canvasId);
        if (!canvas) {
            return;
        }

        if (this.charts[key]) {
            this.charts[key].data.labels = config.data.labels;
            config.data.datasets.forEach(function (dataset, index) {
                var current = this.charts[key].data.datasets[index];
                if (!current) {
                    this.charts[key].data.datasets[index] = dataset;
                    return;
                }
                current.data = dataset.data;
                if (dataset.backgroundColor) {
                    current.backgroundColor = dataset.backgroundColor;
                }
                if (dataset.borderColor) {
                    current.borderColor = dataset.borderColor;
                }
            }.bind(this));
            this.charts[key].update();
            return;
        }

        this.charts[key] = new Chart(canvas.getContext('2d'), config);
    };

    Dashboard.prototype.renderCharts = function (metrics) {
        var products = metrics.products || [];
        var trend = metrics.trend || [];
        var labels = products.map(function (row) { return row.product_name; });
        var produced = products.map(function (row) { return row.produced_qty; });
        var defective = products.map(function (row) { return row.defective_qty; });
        var defectRates = products.map(function (row) { return row.defect_rate; });
        var defectColors = products.map(function (row) {
            return row.alert ? '#e35d6a' : '#3d8bfd';
        });

        this.upsertChart('production', '#chart-production', {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: produced,
                    backgroundColor: COLORS.slice(0, labels.length),
                    borderColor: '#171e26',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { color: MUTED, padding: 14 }
                    }
                }
            }
        });

        this.upsertChart('volume', '#chart-volume', {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Produzido',
                        data: produced,
                        backgroundColor: 'rgba(61, 139, 253, 0.8)'
                    },
                    {
                        label: 'Defeituosos',
                        data: defective,
                        backgroundColor: 'rgba(227, 93, 106, 0.85)'
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { color: MUTED }
                    }
                },
                scales: {
                    x: Object.assign({ stacked: false }, axisDefaults(), {
                        grid: { display: false }
                    }),
                    y: Object.assign({ beginAtZero: true }, axisDefaults())
                }
            }
        });

        this.upsertChart('defectRate', '#chart-defect-rate', {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [{
                    label: 'Taxa %',
                    data: defectRates,
                    backgroundColor: defectColors
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function (context) {
                                return formatPercent(context.parsed.y);
                            }
                        }
                    }
                },
                scales: {
                    x: Object.assign({}, axisDefaults(), { grid: { display: false } }),
                    y: Object.assign({ beginAtZero: true }, axisDefaults(), {
                        ticks: {
                            color: MUTED,
                            callback: function (value) { return value + '%'; }
                        }
                    })
                }
            }
        });

        this.upsertChart('trend', '#chart-trend', {
            type: 'line',
            data: {
                labels: trend.map(function (row) { return row.date; }),
                datasets: [
                    {
                        label: 'Produzido',
                        data: trend.map(function (row) { return row.produced_qty; }),
                        borderColor: '#3d8bfd',
                        backgroundColor: 'rgba(61, 139, 253, 0.15)',
                        tension: 0.25,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Taxa defeitos %',
                        data: trend.map(function (row) { return row.defect_rate; }),
                        borderColor: '#e35d6a',
                        backgroundColor: 'transparent',
                        tension: 0.25,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { color: MUTED }
                    }
                },
                scales: {
                    x: Object.assign({}, axisDefaults(), {
                        ticks: {
                            color: MUTED,
                            maxRotation: 45,
                            minRotation: 0,
                            autoSkip: true,
                            maxTicksLimit: 10
                        },
                        grid: { display: false }
                    }),
                    y: Object.assign({
                        beginAtZero: true,
                        position: 'left',
                        title: { display: true, text: 'Produzido', color: MUTED }
                    }, axisDefaults()),
                    y1: Object.assign({
                        beginAtZero: true,
                        position: 'right',
                        grid: { drawOnChartArea: false },
                        title: { display: true, text: 'Taxa %', color: MUTED },
                        ticks: {
                            color: MUTED,
                            callback: function (value) { return value + '%'; }
                        }
                    })
                }
            }
        });
    };

    Dashboard.prototype.connectEcho = function () {
        var key = this.root.dataset.pusherKey;
        var cluster = this.root.dataset.pusherCluster || 'mt1';
        var self = this;

        if (!key || typeof Echo === 'undefined') {
            return;
        }

        window.Pusher = Pusher;
        window.Echo = new Echo({
            broadcaster: 'pusher',
            key: key,
            cluster: cluster,
            forceTLS: true
        });

        window.Echo.channel('production')
            .listen('.updated', function () {
                self.fetchMetrics();
            });
    };

    window.AXDashboard = {
        init: function (root, metrics) {
            return new Dashboard(root, metrics);
        }
    };
})(window);
