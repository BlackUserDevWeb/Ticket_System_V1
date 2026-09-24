import { HttpErrorResponse, HttpInterceptorFn } from "@angular/common/http";
import { catchError, throwError } from "rxjs";

/** Ajoute le Bearer token Sanctum ; sur 401 -> retour login (session expiree). */
export const authInterceptor: HttpInterceptorFn = (req, next) => {
  const token = localStorage.getItem("tf-token");
  const authReq = token ? req.clone({ setHeaders: { Authorization: `Bearer ${token}` } }) : req;
  return next(authReq).pipe(
    catchError((err: HttpErrorResponse) => {
      if (err.status === 401 && location.pathname !== "/login") {
        localStorage.removeItem("tf-token");
        location.assign("/login");
      }
      return throwError(() => err);
    }),
  );
};
