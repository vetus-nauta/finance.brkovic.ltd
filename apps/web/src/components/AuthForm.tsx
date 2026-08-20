"use client";

import { useEffect, useMemo, useState } from "react";
import { getPublicEnv, hasSupabasePublicEnv } from "@/lib/env";
import { routes } from "@/lib/routes";
import { createClient } from "@/lib/supabase/browser";

const OTP_RESEND_SECONDS = 60;

function cooldownKey(email: string) {
  return `findesk:auth:next-code-request:${email.trim().toLowerCase()}`;
}

function authErrorMessage(error: unknown) {
  const message = error instanceof Error ? error.message : String(error || "");
  const lower = message.toLowerCase();

  if (lower.includes("security") || lower.includes("rate") || lower.includes("limit")) {
    return "Код уже запрошен слишком часто. Подождите немного и используйте последнее письмо.";
  }

  return message || "Не удалось отправить код.";
}

export function AuthForm() {
  const env = getPublicEnv();
  const ready = hasSupabasePublicEnv(env);
  const [email, setEmail] = useState("vetus.nauta@gmail.com");
  const [code, setCode] = useState("");
  const [isCodeSent, setIsCodeSent] = useState(false);
  const [cooldownUntil, setCooldownUntil] = useState(0);
  const [now, setNow] = useState(() => Date.now());
  const [status, setStatus] = useState<string>("");
  const [isSubmitting, setIsSubmitting] = useState(false);
  const resendSeconds = Math.max(0, Math.ceil((cooldownUntil - now) / 1000));
  const canRequestCode = ready && !isSubmitting && resendSeconds === 0;
  const normalizedEmail = useMemo(() => email.trim().toLowerCase(), [email]);

  useEffect(() => {
    const interval = window.setInterval(() => setNow(Date.now()), 1000);
    return () => window.clearInterval(interval);
  }, []);

  useEffect(() => {
    const stored = window.localStorage.getItem(cooldownKey(normalizedEmail));
    setCooldownUntil(stored ? Number(stored) || 0 : 0);
  }, [normalizedEmail]);

  function startCooldown(seconds = OTP_RESEND_SECONDS) {
    const until = Date.now() + seconds * 1000;
    window.localStorage.setItem(cooldownKey(normalizedEmail), String(until));
    setCooldownUntil(until);
    setNow(Date.now());
  }

  async function sendOtpRequest() {
    if (!canRequestCode) {
      setStatus(`Код уже запрошен. Повторная отправка через ${resendSeconds} сек.`);
      return;
    }

    setIsSubmitting(true);
    setStatus("");

    try {
      const supabase = createClient();
      const { error } = await supabase.auth.signInWithOtp({
        email,
        options: {
          emailRedirectTo: `${window.location.origin}${routes.authCallback}`
        }
      });

      if (error) {
        startCooldown();
        setStatus(authErrorMessage(error));
        return;
      }

      startCooldown();
      setIsCodeSent(true);
      setStatus("Код отправлен. Введите 8 цифр из письма, чтобы открыть FinDesk.");
    } catch (error) {
      startCooldown();
      setStatus(authErrorMessage(error));
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handleSubmit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();

    if (isCodeSent) {
      await verifyCode();
      return;
    }

    await sendOtpRequest();
  }

  async function verifyCode() {
    setIsSubmitting(true);
    setStatus("");

    try {
      const supabase = createClient();
      const { error } = await supabase.auth.verifyOtp({
        email,
        token: code.trim(),
        type: "email"
      });

      if (error) {
        setStatus("Код не принят. Проверьте 8 цифр из последнего письма или запросите новый код.");
        return;
      }

      window.location.assign(routes.hall);
    } catch (error) {
      setStatus(error instanceof Error ? error.message : "Не удалось проверить код.");
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <form className="auth-form" onSubmit={handleSubmit}>
      <label htmlFor="email">Email для входа</label>
      <div className="auth-row">
        <input
          id="email"
          name="email"
          type="email"
          autoComplete="email"
          value={email}
          onChange={(event) => setEmail(event.target.value)}
          disabled={!ready || isSubmitting}
          required
        />
        <button
          type={isCodeSent ? "button" : "submit"}
          onClick={isCodeSent ? sendOtpRequest : undefined}
          disabled={!canRequestCode}
        >
          {isSubmitting && !isCodeSent
            ? "Отправляем"
            : isCodeSent
              ? resendSeconds > 0
                ? `Повторить через ${resendSeconds}`
                : "Отправить заново"
              : "Получить код"}
        </button>
      </div>
      {isCodeSent ? (
        <div className="auth-code-block">
          <label htmlFor="otp-code">Код из письма</label>
          <div className="auth-row">
            <input
              id="otp-code"
              name="otp-code"
              type="text"
              inputMode="numeric"
              autoComplete="one-time-code"
              value={code}
              onChange={(event) => setCode(event.target.value.replace(/\D/g, "").slice(0, 8))}
              disabled={!ready || isSubmitting}
              placeholder="12345678"
              required
            />
            <button
              type="button"
              className="primary-action"
              onClick={verifyCode}
              disabled={!ready || isSubmitting || code.trim().length < 6}
            >
              {isSubmitting ? "Проверяем" : "Войти"}
            </button>
          </div>
        </div>
      ) : null}
      <p className={ready ? "form-note" : "form-note error"}>
        {ready
          ? status || "Введите email, получите код письмом и введите его здесь."
          : "Нужно заполнить NEXT_PUBLIC_SUPABASE_URL и NEXT_PUBLIC_SUPABASE_PUBLISHABLE_KEY."}
      </p>
    </form>
  );
}
