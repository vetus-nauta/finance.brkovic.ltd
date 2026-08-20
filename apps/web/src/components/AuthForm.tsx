"use client";

import { useState } from "react";
import { getPublicEnv, hasSupabasePublicEnv } from "@/lib/env";
import { routes } from "@/lib/routes";
import { createClient } from "@/lib/supabase/browser";

export function AuthForm() {
  const env = getPublicEnv();
  const ready = hasSupabasePublicEnv(env);
  const [email, setEmail] = useState("vetus.nauta@gmail.com");
  const [code, setCode] = useState("");
  const [isCodeSent, setIsCodeSent] = useState(false);
  const [status, setStatus] = useState<string>("");
  const [isSubmitting, setIsSubmitting] = useState(false);

  async function sendOtpRequest() {
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
        setStatus(error.message);
        return;
      }

      setIsCodeSent(true);
      setStatus("Код отправлен. Введите 8 цифр из письма, чтобы открыть FinDesk.");
    } catch (error) {
      setStatus(error instanceof Error ? error.message : "Не удалось отправить код.");
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
          disabled={!ready || isSubmitting}
        >
          {isSubmitting && !isCodeSent
            ? "Отправляем"
            : isCodeSent
              ? "Отправить заново"
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
