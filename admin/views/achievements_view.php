<?php
// admin/views/achievements_view.php

// Assumes $achievementsChartLabels and $achievementsChartData are available from fetch_content.php
$achievementsChartLabels = $achievementsChartLabels ?? [];
$achievementsChartData = $achievementsChartData ?? [];
?>

<div class="dashboard-content achievements-content">
    <h3>User Job Posting Achievements (Last 30 Days)</h3>
    <!-- <p>Each job posted earns ₹3. This graph shows the daily earnings per user based on their job posts.</p> -->

    <?php if (!empty($achievementsChartData)): ?>
        <div class="chart-container" style="height: 500px;">
            <canvas id="achievementsChart"></canvas>
        </div>
    <?php else: ?>
        <p>No job posting data available to display achievements yet.</p>
    <?php endif; ?>
</div>

<?php if (!empty($achievementsChartData)): ?>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const achievementsChartLabels = <?= json_encode($achievementsChartLabels) ?>;
        const achievementsChartDatasets = <?= json_encode($achievementsChartData) ?>;

        const achievementsCtx = document.getElementById('achievementsChart').getContext('2d');
        new Chart(achievementsCtx, {
            type: 'line',
            data: {
                labels: achievementsChartLabels,
                datasets: achievementsChartDatasets
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Daily Earnings (₹)'
                        },
                        ticks: {
                            // Format ticks as currency if desired, e.g., using Intl.NumberFormat
                            callback: function(value, index, values) {
                                return '₹' + value;
                            }
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Date'
                        }
                    }
                },
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.dataset.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed.y !== null) {
                                    label += '₹' + context.parsed.y;
                                }
                                return label;
                            }
                        }
                    },
                    title: {
                        display: true,
                        text: 'User Daily Job Post Earnings'
                    }
                }
            }
        });
    });
</script>
<?php endif; ?>

<style>
    .achievements-content h3 {
        margin-top: 0;
        margin-bottom: 10px;
        color: #005fa3;
    }
    .achievements-content p {
        margin-bottom: 20px;
        font-size: 0.95em;
        color: #555;
    }
    .chart-container {
        margin-top: 20px;
        padding: 15px;
        background-color: #f9f9f9;
        border: 1px solid #ddd;
        border-radius: 5px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        position: relative; /* Needed for maintainAspectRatio: false */
    }
</style>
