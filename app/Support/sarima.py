"""JSON stdin/stdout boundary for monthly SARIMA forecasts; no personal data."""

import json
import math
import sys
import warnings


def forecast_series(values, configuration):
    import numpy as np
    import statsmodels
    from statsmodels.tsa.statespace.sarimax import SARIMAX

    season = int(configuration["seasonal_order"][3])
    holdout = min(6, int(configuration["forecast_horizon"]))
    if len(values) < max(int(configuration["minimum_months"]), 2 * season + holdout):
        raise ValueError("Insufficient complete monthly observations for training and evaluation.")
    if any(value is None or not math.isfinite(value) or value < 0 for value in values):
        raise ValueError("Missing or invalid monthly observations must be resolved before forecasting.")
    if len(set(values)) < 3 or sum(value > 0 for value in values) < season:
        raise ValueError("Too little variation or activity to estimate this seasonal model reliably.")

    def fit(history):
        return SARIMAX(
            np.log1p(np.asarray(history, dtype=float)),
            order=tuple(configuration["order"]),
            seasonal_order=tuple(configuration["seasonal_order"]),
            enforce_stationarity=True,
            enforce_invertibility=True,
        ).fit(disp=False, maxiter=150)

    with warnings.catch_warnings(record=True) as captured:
        warnings.simplefilter("always")
        evaluation_fit = fit(values[:-holdout])
        predicted = np.maximum(0, np.expm1(evaluation_fit.get_forecast(steps=holdout).predicted_mean))
        actual = np.asarray(values[-holdout:], dtype=float)
        baseline = np.asarray([values[len(values) - holdout + i - season] for i in range(holdout)])
        mae = float(np.mean(np.abs(actual - predicted)))
        baseline_mae = float(np.mean(np.abs(actual - baseline)))
        fitted = fit(values)
        forecast = fitted.get_forecast(steps=int(configuration["forecast_horizon"]))
        point = np.maximum(0, np.expm1(forecast.predicted_mean))
        interval = np.maximum(0, np.expm1(np.asarray(forecast.conf_int(alpha=0.05))))

    if not np.all(np.isfinite(point)) or not np.all(np.isfinite(interval)) or not math.isfinite(mae):
        raise ValueError("The fitted model produced unstable estimates.")
    converged = bool(fitted.mle_retvals.get("converged", False) and evaluation_fit.mle_retvals.get("converged", False))
    reliable = converged and mae < baseline_mae
    return {
        "forecast": [
            {"estimate": round(float(value), 2), "lower": round(float(interval[i, 0]), 2), "upper": round(float(interval[i, 1]), 2)}
            for i, value in enumerate(point)
        ],
        "evaluation": {
            "mae": round(mae, 3), "seasonal_baseline_mae": round(baseline_mae, 3),
            "holdout_months": holdout, "converged": converged, "reliable": reliable,
            "warnings": sorted(set(str(item.message) for item in captured)),
            "statsmodels_version": statsmodels.__version__,
            "parameters": {name: float(value) for name, value in zip(fitted.param_names, fitted.params)},
            "transformation": "log1p; point estimates are back-transformed medians",
        },
    }


def main():
    payload = json.load(sys.stdin)
    results = {}
    for metric in ("count", "revenue"):
        try:
            results[metric] = forecast_series([row[metric] for row in payload["history"]], payload["configuration"])
        except (ValueError, RuntimeError) as error:
            results[metric] = {"error": str(error)}
    json.dump(results, sys.stdout, allow_nan=False)


if __name__ == "__main__":
    try:
        main()
    except ImportError:
        json.dump({"error": "The statsmodels forecasting runtime is not installed."}, sys.stdout)
        sys.exit(2)
    except Exception as error:
        json.dump({"error": "Forecast generation failed: " + type(error).__name__}, sys.stdout)
        sys.exit(1)
