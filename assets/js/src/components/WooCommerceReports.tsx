import React, { useState, useEffect } from 'react';
import { __ } from '@wordpress/i18n';
import { Button, Spinner } from '@wordpress/components';
import VisualizationEngine, { ChartType } from './VisualizationEngine';

const REPORT_TYPES = [
  { label: 'Sales', value: 'sales' },
  { label: 'Orders', value: 'orders' },
  { label: 'Top Sellers', value: 'top_sellers' },
];

const DATE_RANGES = [
  { label: 'Last 7 days', value: '7d' },
  { label: 'Last 30 days', value: '30d' },
  { label: 'Custom', value: 'custom' },
];

export const WooCommerceReports: React.FC = () => {
  const [reportType, setReportType] = useState('sales');
  const [dateRange, setDateRange] = useState('7d');
  const [customStart, setCustomStart] = useState('');
  const [customEnd, setCustomEnd] = useState('');
  const [salesData, setSalesData] = useState<any>(null);
  const [loadingSales, setLoadingSales] = useState(false);
  const [salesError, setSalesError] = useState<string | null>(null);

  // Helper to get date params
  const getDateParams = () => {
    if (dateRange === 'custom' && customStart && customEnd) {
      return `?date_min=${customStart}&date_max=${customEnd}`;
    }
    if (dateRange === '7d') {
      const end = new Date();
      const start = new Date();
      start.setDate(end.getDate() - 6);
      return `?date_min=${start.toISOString().slice(0, 10)}&date_max=${end.toISOString().slice(0, 10)}`;
    }
    if (dateRange === '30d') {
      const end = new Date();
      const start = new Date();
      start.setDate(end.getDate() - 29);
      return `?date_min=${start.toISOString().slice(0, 10)}&date_max=${end.toISOString().slice(0, 10)}`;
    }
    return '';
  };

  useEffect(() => {
    console.log('WooCommerceReports useEffect', { reportType, dateRange, customStart, customEnd, loadingSales });
    // Only fetch for Sales for now
    if (reportType !== 'sales') {
      setSalesData(null);
      setSalesError(__('This report type is coming soon!', 'ai-command-palette'));
      return;
    }
    setLoadingSales(true);
    setSalesError(null);
    setSalesData(null);
    const params = getDateParams();
    fetch(`/wp-json/wc/v3/reports/sales${params}`, {
      credentials: 'include',
      headers: {
        'Content-Type': 'application/json',
      },
    })
      .then(async (res) => {
        if (!res.ok) {
          const errorText = await res.text();
          throw new Error(errorText || 'Failed to fetch sales data');
        }
        return res.json();
      })
      .then((data) => {
        // Transform WooCommerce sales report data to Chart.js format
        // WooCommerce returns totals, e.g. { total_sales: "1234.56", ... }
        const chartData = {
          labels: ['Total Sales'],
          datasets: [
            {
              label: 'Sales',
              data: [parseFloat(data.total_sales || 0)],
              backgroundColor: 'rgba(54, 162, 235, 0.5)',
              borderColor: 'rgba(54, 162, 235, 1)',
              borderWidth: 2,
            },
          ],
        };
        setSalesData(chartData);
        setLoadingSales(false);
      })
      .catch((err) => {
        setSalesError(err.message || 'Could not load sales data.');
        setLoadingSales(false);
      });
  }, [reportType, dateRange, customStart, customEnd]);

  return (
    <div className="mt-6">
      <form className="flex flex-col md:flex-row gap-4 items-end mb-4" aria-label={__('Report controls', 'ai-command-palette')}>
        <div>
          <label htmlFor="report-type" className="block text-sm font-medium mb-1">
            {__('Report Type', 'ai-command-palette')}
          </label>
          <select
            id="report-type"
            value={reportType}
            onChange={e => setReportType(e.target.value)}
            className="block w-full rounded border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-200"
            aria-describedby="report-help"
          >
            {REPORT_TYPES.map(rt => (
              <option key={rt.value} value={rt.value}>{rt.label}</option>
            ))}
          </select>
        </div>
        <div>
          <label htmlFor="date-range" className="block text-sm font-medium mb-1">
            {__('Date Range', 'ai-command-palette')}
          </label>
          <select
            id="date-range"
            value={dateRange}
            onChange={e => setDateRange(e.target.value)}
            className="block w-full rounded border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-200"
          >
            {DATE_RANGES.map(dr => (
              <option key={dr.value} value={dr.value}>{dr.label}</option>
            ))}
          </select>
        </div>
        {dateRange === 'custom' && (
          <div className="flex gap-2 items-end">
            <div>
              <label htmlFor="custom-start" className="block text-xs font-medium mb-1">
                {__('Start', 'ai-command-palette')}
              </label>
              <input
                id="custom-start"
                type="date"
                value={customStart}
                onChange={e => setCustomStart(e.target.value)}
                className="block w-full rounded border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-200"
                aria-label={__('Custom start date', 'ai-command-palette')}
              />
            </div>
            <div>
              <label htmlFor="custom-end" className="block text-xs font-medium mb-1">
                {__('End', 'ai-command-palette')}
              </label>
              <input
                id="custom-end"
                type="date"
                value={customEnd}
                onChange={e => setCustomEnd(e.target.value)}
                className="block w-full rounded border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-200"
                aria-label={__('Custom end date', 'ai-command-palette')}
              />
            </div>
          </div>
        )}
      </form>
      <div id="report-help" className="screen-reader-text">
        {__('Select report type and date range to view data visualization', 'ai-command-palette')}
      </div>
      {loadingSales && (
        <div
          className="animate-pulse text-gray-500"
          aria-live="polite"
        >
          {__('Loading sales data...', 'ai-command-palette')}
        </div>
      )}
      {salesError && (
        <div
          className="text-red-600"
          role="alert"
          aria-live="assertive"
        >
          {salesError}
        </div>
      )}
      {!loadingSales && !salesError && !salesData && (
        <div className="text-gray-500">
          {__('No data to display for this period.', 'ai-command-palette')}
        </div>
      )}
      {salesData && (
        <VisualizationEngine
          chartType={"bar" as ChartType}
          data={salesData}
          title={__('WooCommerce Sales (Live Data)', 'ai-command-palette')}
          aria-label={__('Sales data visualization', 'ai-command-palette')}
        />
      )}
    </div>
  );
};

export default WooCommerceReports;