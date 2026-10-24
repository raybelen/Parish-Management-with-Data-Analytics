"""Count-only SARIMA with chronological evaluation and empirical planning ranges."""
import json
import os
import sys
import warnings
os.environ.setdefault("OPENBLAS_NUM_THREADS", "1")
os.environ.setdefault("OMP_NUM_THREADS", "1")

def forecast_series(history, configuration):
    import numpy as np
    import statsmodels
    from statsmodels.tsa.statespace.sarimax import SARIMAX
    values = np.array([row["count"] for row in history], dtype=float)
    months = [row["month"] for row in history]
    if len(values) < 72 or not np.isfinite(values).all() or (values < 0).any():
        raise ValueError("At least 72 consecutive confirmed months with nonnegative counts are required.")
    if not (values == np.floor(values)).all():
        raise ValueError("Service counts must be whole numbers.")
    ordinals = [int(m[:4]) * 12 + int(m[5:]) for m in months]
    if any(b - a != 1 for a, b in zip(ordinals, ordinals[1:])):
        raise ValueError("Monthly history must be consecutive.")
    if len(set(values)) < 3 or sum(values > 0) < 12:
        raise ValueError("Too little activity or variation for this seasonal model.")
    cache = {}
    logged = bool(configuration["log_transform"])
    def predict(length):
        if length in cache:
            return cache[length]
        training = np.log1p(values[:length]) if logged else values[:length]
        model = SARIMAX(training, order=tuple(configuration["order"]),
                        seasonal_order=tuple(configuration["seasonal_order"]), trend="n",
                        enforce_stationarity=True, enforce_invertibility=True)
        fitted = model.fit(disp=False, maxiter=250, cov_type="none")
        if not fitted.mle_retvals.get("converged", False):
            fitted = model.fit(disp=False, maxiter=350, method="powell", cov_type="none")
        if not fitted.mle_retvals.get("converged", False):
            raise ValueError("The SARIMA fit did not converge. Forecast unavailable.")
        prediction = np.asarray(fitted.forecast(6))
        if logged:
            prediction = np.expm1(prediction)
        if not np.isfinite(prediction).all():
            raise ValueError("The model produced unstable estimates.")
        cache[length] = np.maximum(0, prediction)
        return cache[length]
    with warnings.catch_warnings():
        warnings.simplefilter("ignore")
        calibration = {h: [] for h in range(1, 7)}
        test = {h: [] for h in range(1, 7)}
        seasonal_errors, mean_errors = [], []
        n = len(values)
        for target in range(n - 24, n):
            for h in range(1, 7):
                length = target - h + 1
                point, actual = float(predict(length)[h - 1]), float(values[target])
                if target < n - 12:
                    calibration[h].append(abs(point - actual))
                else:
                    test[h].append((point, actual))
                    seasonal_errors.append(abs(values[target - 12] - actual))
                    mean_errors.append(abs(float(np.mean(values[length - 12:length])) - actual))
        radii = {h: max(calibration[h]) for h in calibration}
        errors = [abs(p - a) for rows in test.values() for p, a in rows]
        mae = float(np.mean(errors))
        baseline_mae, mean_mae = float(np.mean(seasonal_errors)), float(np.mean(mean_errors))
        points = predict(n)
    return {
        "forecast": [{"estimate": float(p), "lower": max(0.0, float(p)-radii[h]), "upper": float(p)+radii[h]} for h,p in enumerate(points, 1)],
        "evaluation": {
            "mae": mae, "rmse": float(np.sqrt(np.mean(np.square(errors)))),
            "seasonal_baseline_mae": baseline_mae, "recent_mean_mae": mean_mae,
            "converged": True, "outperformed_baselines": mae < min(baseline_mae, mean_mae),
            "test_start": months[-12], "test_end": months[-1],
            "calibration_start": months[-24], "calibration_end": months[-13],
            "test_predictions": 72, "test_months": 12,
            "by_horizon": [{"horizon": h, "mae": float(np.mean([abs(p-a) for p,a in test[h]])),
                            "covered": sum(max(0, p-radii[h]) <= a <= p+radii[h] for p,a in test[h]),
                            "total": 12, "radius": radii[h]} for h in range(1, 7)],
            "range_method": "Largest absolute error in 12 calibration months at each lead time; no guaranteed future coverage.",
            "statsmodels_version": statsmodels.__version__,
            "transformation": "log1p; back-transformed median" if logged else "none",
        },
    }

def main():
    payload = json.load(sys.stdin)
    json.dump({"count": forecast_series(payload["history"], payload["configuration"])}, sys.stdout, allow_nan=False)

if __name__ == "__main__":
    try:
        main()
    except ValueError as error:
        json.dump({"count": {"error": str(error)}}, sys.stdout)
    except ImportError:
        json.dump({"error": "The statsmodels forecasting runtime is not installed."}, sys.stdout)
        sys.exit(2)
    except Exception as error:
        json.dump({"error": "Forecast generation failed: " + type(error).__name__}, sys.stdout)
        sys.exit(1)
