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
            const newData = JSON.parse(event.data);
            this.updateChart(newData);
        };
    }

    updateChart(newData) {
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
}
