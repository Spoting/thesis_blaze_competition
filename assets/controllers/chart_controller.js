import { Controller } from '@hotwired/stimulus';
import { Chart } from 'chart.js';

export default class extends Controller {
    static values = {
        mercureUrl: String
    }

    connect() {
        this.canvas = this.element.querySelector('canvas');

        // Try every 100ms until the chart is available
        this.chartPoll = setInterval(() => {
            const chart = Chart.getChart(this.canvas);
            if (chart) {
                clearInterval(this.chartPoll);
                this.chartInstance = chart;

                this.subscribeToMercure();
            }
        }, 100);
    }

    disconnect() {
        if (this.eventSource) {
            this.eventSource.close();
        }
        if (this.chartPoll) {
            clearInterval(this.chartPoll);
        }
    }

    subscribeToMercure() {
        this.eventSource = new EventSource(this.mercureUrlValue, { withCredentials: true });
        console.log('Subscribed to ' + this.mercureUrlValue);
        this.eventSource.onmessage = (event) => {
            const update = JSON.parse(event.data);

            if (update.type === 'snapshot') {
                this.updateSnapshot(update.data);
            } else if (update.type === 'status') {
                this.updateAnnotation(update.annotation);
            }

        };
    }

    updateSnapshot(newData) {
        if (!this.chartInstance) return;

        // Append the new label
        this.chartInstance.data.labels.push(...newData.labels);

        // Append new point to each dataset
        newData.datasets.forEach((newDs, idx) => {
            if (this.chartInstance.data.datasets[idx]) {
                this.chartInstance.data.datasets[idx].label = newDs.label;
                this.chartInstance.data.datasets[idx].data.push(...newDs.data);
            }
        });

        console.log('Mercure Updated  ' + newData);
        this.chartInstance.update();
    }


    updateAnnotation(annotation) {
        if (!this.chartInstance || !annotation) return;

        const {
            id,
            value,
            borderColor,
            labelContent,
            yAdjust = 0,
        } = annotation;

        // Ensure the annotation plugin exists
        const annotations = this.chartInstance.options.plugins.annotation.annotations;

        // Create the annotation config (matches your PHP-generated values)
        annotations[id] = {
            type: 'line',
            mode: 'vertical',
            scaleID: 'x',
            value: value, // must match a string in chart.data.labels if using category scale
            borderColor: borderColor,
            borderWidth: 2,
            label: {
                display: true,
                enabled: true,
                content: labelContent,
                backgroundColor: borderColor,
                color: 'white',
                font: {
                    size: 10,
                },
                position: 'end',
                yAdjust: yAdjust,
            },
        };

        console.log('[Chart] Annotation added:', id, annotation);
        this.chartInstance.update();
    }
}
