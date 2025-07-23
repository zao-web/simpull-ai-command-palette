import React, { useEffect } from 'react';
import { Line, Bar, Pie, Doughnut, Scatter, Radar } from 'react-chartjs-2';
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  BarElement,
  ArcElement,
  RadialLinearScale,
  Title,
  Tooltip,
  Legend,
  Filler,
} from 'chart.js';

ChartJS.register(
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  BarElement,
  ArcElement,
  RadialLinearScale,
  Title,
  Tooltip,
  Legend,
  Filler
);

export type ChartType = 'line' | 'bar' | 'pie' | 'doughnut' | 'scatter' | 'radar';

interface VisualizationEngineProps {
  chartType: ChartType;
  data: any;
  title?: string;
  height?: number;
  width?: number;
  options?: any;
}

const VisualizationEngine: React.FC<VisualizationEngineProps> = ({
  chartType,
  data,
  title,
  height = 400,
  width = 600,
  options = {}
}) => {
  useEffect(() => {
    console.log('VisualizationEngine useEffect', { chartType, data, title, height, width, options });
  }, [chartType, data, title, height, width, options]);

  const defaultOptions = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: {
      legend: {
        position: 'top' as const,
      },
      title: {
        display: !!title,
        text: title,
      },
    },
    scales: {
      y: {
        beginAtZero: true,
      },
    },
  };

  const mergedOptions = { ...defaultOptions, ...options };

  const renderChart = () => {
    switch (chartType) {
      case 'line':
        return <Line data={data} options={mergedOptions} height={height} width={width} />;
      case 'bar':
        return <Bar data={data} options={mergedOptions} height={height} width={width} />;
      case 'pie':
        return <Pie data={data} options={mergedOptions} height={height} width={width} />;
      case 'doughnut':
        return <Doughnut data={data} options={mergedOptions} height={height} width={width} />;
      case 'scatter':
        return <Scatter data={data} options={mergedOptions} height={height} width={width} />;
      case 'radar':
        return <Radar data={data} options={mergedOptions} height={height} width={width} />;
      default:
        return <Bar data={data} options={mergedOptions} height={height} width={width} />;
    }
  };

  return (
    <div className="aicp-visualization" style={{ height, width }}>
      {renderChart()}
    </div>
  );
};

export default VisualizationEngine;