package com.liberou.tv.panel;

import android.app.Activity;
import android.content.Context;
import android.content.SharedPreferences;
import android.content.pm.PackageInfo;
import android.graphics.Bitmap;
import android.graphics.BitmapFactory;
import android.graphics.drawable.BitmapDrawable;
import android.net.wifi.WifiManager;
import android.os.Build;
import android.provider.Settings;
import android.view.Gravity;
import android.view.View;
import java.io.BufferedReader;
import java.io.ByteArrayOutputStream;
import java.io.File;
import java.io.FileOutputStream;
import java.io.InputStream;
import java.io.InputStreamReader;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import org.json.JSONObject;

/** Ponte app ↔ painel PHP LIBEROU. */
public final class PanelClient {

    public static String BASE_URL = "https://painel.liberoutv.online";
    public static String API_TOKEN = "Le4lzASyjli5gJhR3D1zMjeKqMjakFLuBD0wHu0i1oZ6OZbKZEq3HcL8Alcmgjk9";

    private PanelClient() {}

    /** Sync em background (config + download + heartbeat). */
    public static void a(Context ctx) {
        if (ctx == null) {
            return;
        }
        Context app = ctx.getApplicationContext();
        Thread t = new Thread(new SyncJob(app), "liberou-panel-sync");
        t.start();
    }

    /**
     * Dashboard/Login: sincroniza, aplica imagens agora e re-aplica várias vezes
     * (espera o download das imagens terminar).
     */
    public static void c(Activity act) {
        if (act == null) {
            return;
        }
        a(act);
        try {
            act.runOnUiThread(new ApplyCardsJob(act));
        } catch (Throwable ignored) {
        }
        Thread t = new Thread(new DelayedCardsJob(act), "liberou-panel-cards");
        t.start();
    }

    public static void b(Context ctx) {
        try {
            String base = BASE_URL;
            SharedPreferences panelPrefs = ctx.getSharedPreferences("liberou_panel", 0);
            String override = panelPrefs.getString("base_url", "");
            if (override != null && override.length() > 8) {
                base = override;
            }
            if (base.indexOf("SEU-DOMINIO") >= 0) {
                return;
            }

            // Heartbeat cedo: dispositivos aparecem mesmo se download de imagem falhar
            try {
                heartbeat(ctx, base);
            } catch (Throwable ignored) {
            }

            String cfgUrl = trimSlash(base) + "/api/config.php";
            String json = httpGet(cfgUrl);
            if (json == null || json.length() < 5) {
                return;
            }
            JSONObject o = new JSONObject(json);
            if (!o.optBoolean("ok", false)) {
                return;
            }

            SharedPreferences.Editor pe = panelPrefs.edit();
            pe.putString("config_json", json);
            pe.putLong("config_at", System.currentTimeMillis());
            pe.apply();

            String dns = o.optString("login_dns", "");
            boolean force = o.optBoolean("force_dns", true);
            if (dns != null && dns.length() > 5 && force) {
                ctx.getSharedPreferences("loginPrefsserverurl", 0)
                        .edit()
                        .putString("serverUrlMAG", dns)
                        .apply();
                ctx.getSharedPreferences("serverUrlDNS", 0)
                        .edit()
                        .putString("serverUrlMAG", dns)
                        .putString("dns", dns)
                        .apply();
                pe = panelPrefs.edit();
                pe.putString("login_dns", dns);
                pe.apply();
            }

            JSONObject cards = o.optJSONObject("cards");
            if (cards != null) {
                downloadCard(ctx, cards.optString("live", ""), "card_live.png");
                downloadCard(ctx, cards.optString("movies", ""), "card_movies.png");
                downloadCard(ctx, cards.optString("series", ""), "card_series.png");
            }

            String dashBg = o.optString("dashboard_background", "");
            if (dashBg != null && dashBg.length() > 8) {
                downloadCard(ctx, dashBg, "dashboard_bg.jpg");
            }

            String loginBg = o.optString("login_background", "");
            if (loginBg != null && loginBg.length() > 8) {
                downloadCard(ctx, loginBg, "login_bg.jpg");
            } else {
                deleteCachedCard(ctx, "login_bg.jpg");
            }

            org.json.JSONArray shortcuts = o.optJSONArray("shortcuts");
            if (shortcuts != null) {
                SharedPreferences.Editor se = panelPrefs.edit();
                for (int i = 0; i < shortcuts.length() && i < 3; i++) {
                    JSONObject s = shortcuts.optJSONObject(i);
                    if (s == null) {
                        continue;
                    }
                    int id = s.optInt("id", i + 1);
                    String cat = s.optString("category", "");
                    String type = s.optString("type", "series");
                    String img = s.optString("image", "");
                    if (cat != null && cat.length() > 0) {
                        se.putString("shortcut_" + id + "_cat", cat);
                    }
                    if (type != null && type.length() > 0) {
                        se.putString("shortcut_" + id + "_type", type);
                    }
                    if (img != null && img.length() > 8) {
                        downloadCard(ctx, img, "shortcut_" + id + ".png");
                    } else {
                        deleteCachedCard(ctx, "shortcut_" + id + ".png");
                    }
                }
                se.apply();
            }

            // segundo heartbeat após sync (confirma app ainda vivo)
            try {
                heartbeat(ctx, base);
            } catch (Throwable ignored) {
            }
        } catch (Throwable ignored) {
        }
    }

    public static void openShortcut(Context ctx, int index) {
        if (ctx == null) {
            return;
        }
        String defCat = "PREMIERE";
        String defType = "live";
        if (index == 2) {
            defCat = "TELENOVELAS";
            defType = "series";
        } else if (index == 3) {
            defCat = "ANIMACAO";
            defType = "series";
        }
        try {
            SharedPreferences p = ctx.getSharedPreferences("liberou_panel", 0);
            String cat = p.getString("shortcut_" + index + "_cat", defCat);
            String type = p.getString("shortcut_" + index + "_type", defType);
            if (cat == null || cat.length() == 0) {
                cat = defCat;
            }
            if (type == null || type.length() == 0) {
                type = defType;
            }
            if ("live".equalsIgnoreCase(type)) {
                com.liberou.tv.miscelleneious.CategoryShortcut.c(ctx, cat);
            } else {
                com.liberou.tv.miscelleneious.CategoryShortcut.d(ctx, cat);
            }
        } catch (Throwable ignored) {
            try {
                if ("live".equalsIgnoreCase(defType)) {
                    com.liberou.tv.miscelleneious.CategoryShortcut.c(ctx, defCat);
                } else {
                    com.liberou.tv.miscelleneious.CategoryShortcut.d(ctx, defCat);
                }
            } catch (Throwable ignored2) {
            }
        }
    }

    public static void d(Activity act) {
        if (act == null) {
            return;
        }
        try {
            File dir = new File(act.getFilesDir(), "liberou_cards");
            applyBg(act.findViewById(0x7f0b04bb), new File(dir, "dashboard_bg.jpg"));
            applyBgRoot(act.findViewById(0x7f0b006c), new File(dir, "login_bg.jpg"));
            applyBg(act.findViewById(0x7f0b03b9), new File(dir, "card_live.png"));
            applyBg(act.findViewById(0x7f0b058e), new File(dir, "card_movies.png"));
            applyBg(act.findViewById(0x7f0b0188), new File(dir, "card_series.png"));
            applyBg(act.findViewById(0x7f0b0228), new File(dir, "shortcut_1.png"));
            applyBg(act.findViewById(0x7f0b055f), new File(dir, "shortcut_2.png"));
            applyBg(act.findViewById(0x7f0b06ea), new File(dir, "shortcut_3.png"));
        } catch (Throwable ignored) {
        }
    }

    private static void applyBgRoot(View v, File f) {
        if (v == null || f == null || !f.exists() || f.length() < 32) {
            return;
        }
        try {
            Bitmap bmp = BitmapFactory.decodeFile(f.getAbsolutePath());
            if (bmp == null) {
                return;
            }
            BitmapDrawable d = new BitmapDrawable(v.getResources(), bmp);
            d.setGravity(Gravity.FILL);
            v.setBackground(d);
        } catch (Throwable ignored) {
        }
    }

    private static void applyBg(View v, File f) {
        if (v == null || f == null || !f.exists() || f.length() < 32) {
            return;
        }
        try {
            Bitmap bmp = BitmapFactory.decodeFile(f.getAbsolutePath());
            if (bmp == null) {
                return;
            }
            BitmapDrawable d = new BitmapDrawable(v.getResources(), bmp);
            d.setGravity(Gravity.FILL);
            v.setBackground(d);
            if (v instanceof android.view.ViewGroup) {
                android.view.ViewGroup vg = (android.view.ViewGroup) v;
                if (vg.getChildCount() > 0) {
                    View child = vg.getChildAt(0);
                    if (child != null) {
                        BitmapDrawable d2 = new BitmapDrawable(v.getResources(), bmp);
                        d2.setGravity(Gravity.FILL);
                        child.setBackground(d2);
                    }
                }
            }
        } catch (Throwable ignored) {
        }
    }

    private static void deleteCachedCard(Context ctx, String fileName) {
        if (ctx == null || fileName == null) {
            return;
        }
        try {
            File f = new File(new File(ctx.getFilesDir(), "liberou_cards"), fileName);
            if (f.exists()) {
                f.delete();
            }
        } catch (Throwable ignored) {
        }
    }

    private static void downloadCard(Context ctx, String url, String fileName) {
        if (url == null || url.length() < 8 || !url.startsWith("http")) {
            return;
        }
        try {
            File dir = new File(ctx.getFilesDir(), "liberou_cards");
            if (!dir.exists()) {
                dir.mkdirs();
            }
            File out = new File(dir, fileName);
            // cache-bust: se a URL mudou, baixa de novo; se igual e arquivo ok, ok
            SharedPreferences p = ctx.getSharedPreferences("liberou_panel", 0);
            String key = "url_" + fileName;
            String prev = p.getString(key, "");
            if (url.equals(prev) && out.exists() && out.length() > 32) {
                return;
            }
            byte[] data = httpBytes(url);
            if (data == null || data.length < 32) {
                return;
            }
            FileOutputStream fos = new FileOutputStream(out);
            fos.write(data);
            fos.close();
            p.edit().putString(key, url).apply();
        } catch (Throwable ignored) {
        }
    }

    private static void heartbeat(Context ctx, String base) {
        try {
            JSONObject body = new JSONObject();
            String androidId = "";
            try {
                androidId = Settings.Secure.getString(ctx.getContentResolver(), "android_id");
            } catch (Throwable ignored) {
            }
            String mac = "";
            try {
                WifiManager wm = (WifiManager) ctx.getApplicationContext().getSystemService("wifi");
                if (wm != null && wm.getConnectionInfo() != null) {
                    mac = wm.getConnectionInfo().getMacAddress();
                }
            } catch (Throwable ignored) {
            }

            String deviceType = "Mobile";
            try {
                String t = "";
                String[] names = new String[] {
                    "loginPrefs", "sharedPreference", "screentype", "pref", "myPref", "preferences"
                };
                for (int i = 0; i < names.length; i++) {
                    String v = ctx.getSharedPreferences(names[i], 0).getString("pref.screen_type", "");
                    if (v != null && v.length() > 0) {
                        t = v;
                        break;
                    }
                }
                if ("TV".equalsIgnoreCase(t)) {
                    deviceType = "TV";
                } else if ("Mobile".equalsIgnoreCase(t)) {
                    deviceType = "Mobile";
                } else {
                    int layout = ctx.getResources().getConfiguration().screenLayout & 15;
                    deviceType = layout >= 3 ? "TV" : "Mobile";
                }
            } catch (Throwable ignored) {
            }

            String username = "";
            String serverUrl = "";
            try {
                username = ctx.getSharedPreferences("loginPrefs", 0).getString("username", "");
                serverUrl = ctx.getSharedPreferences("loginPrefsserverurl", 0).getString("serverUrlMAG", "");
            } catch (Throwable ignored) {
            }

            String appVersion = "";
            try {
                PackageInfo pi = ctx.getPackageManager().getPackageInfo(ctx.getPackageName(), 0);
                appVersion = pi.versionName;
            } catch (Throwable ignored) {
            }

            body.put("mac", mac == null ? "" : mac);
            body.put("android_id", androidId == null ? "" : androidId);
            body.put("device_type", deviceType);
            body.put("model", Build.MODEL == null ? "" : Build.MODEL);
            body.put("manufacturer", Build.MANUFACTURER == null ? "" : Build.MANUFACTURER);
            body.put("android_version", Build.VERSION.RELEASE == null ? "" : Build.VERSION.RELEASE);
            body.put("app_version", appVersion == null ? "" : appVersion);
            body.put("username", username == null ? "" : username);
            body.put("server_url", serverUrl == null ? "" : serverUrl);

            httpPostJson(trimSlash(base) + "/api/heartbeat.php", body.toString());
        } catch (Throwable ignored) {
        }
    }

    private static String trimSlash(String base) {
        if (base == null) {
            return "";
        }
        while (base.endsWith("/")) {
            base = base.substring(0, base.length() - 1);
        }
        return base;
    }

    private static String httpGet(String urlStr) throws Exception {
        HttpURLConnection c = (HttpURLConnection) new URL(urlStr).openConnection();
        c.setConnectTimeout(12000);
        c.setReadTimeout(20000);
        c.setRequestMethod("GET");
        c.setRequestProperty("X-Api-Token", API_TOKEN);
        c.setRequestProperty("User-Agent", "LIBEROU-PanelClient/1.1");
        int code = c.getResponseCode();
        InputStream in = code >= 400 ? c.getErrorStream() : c.getInputStream();
        if (in == null) {
            return null;
        }
        BufferedReader br = new BufferedReader(new InputStreamReader(in, "UTF-8"));
        StringBuilder sb = new StringBuilder();
        String line;
        while ((line = br.readLine()) != null) {
            sb.append(line);
        }
        br.close();
        c.disconnect();
        return sb.toString();
    }

    private static byte[] httpBytes(String urlStr) throws Exception {
        HttpURLConnection c = (HttpURLConnection) new URL(urlStr).openConnection();
        c.setConnectTimeout(15000);
        c.setReadTimeout(45000);
        c.setRequestMethod("GET");
        c.setRequestProperty("User-Agent", "LIBEROU-PanelClient/1.1");
        int code = c.getResponseCode();
        if (code >= 400) {
            c.disconnect();
            return null;
        }
        InputStream in = c.getInputStream();
        ByteArrayOutputStream bos = new ByteArrayOutputStream();
        byte[] buf = new byte[8192];
        int n;
        while ((n = in.read(buf)) >= 0) {
            bos.write(buf, 0, n);
        }
        in.close();
        c.disconnect();
        return bos.toByteArray();
    }

    private static void httpPostJson(String urlStr, String json) throws Exception {
        HttpURLConnection c = (HttpURLConnection) new URL(urlStr).openConnection();
        c.setConnectTimeout(12000);
        c.setReadTimeout(15000);
        c.setRequestMethod("POST");
        c.setDoOutput(true);
        c.setRequestProperty("Content-Type", "application/json; charset=utf-8");
        c.setRequestProperty("X-Api-Token", API_TOKEN);
        c.setRequestProperty("User-Agent", "LIBEROU-PanelClient/1.1");
        byte[] bytes = json.getBytes("UTF-8");
        c.setFixedLengthStreamingMode(bytes.length);
        OutputStream os = c.getOutputStream();
        os.write(bytes);
        os.close();
        c.getResponseCode();
        c.disconnect();
    }

    static final class SyncJob implements Runnable {
        private final Context ctx;

        SyncJob(Context ctx) {
            this.ctx = ctx;
        }

        @Override
        public void run() {
            PanelClient.b(ctx);
        }
    }

    static final class ApplyCardsJob implements Runnable {
        private final Activity act;

        ApplyCardsJob(Activity act) {
            this.act = act;
        }

        @Override
        public void run() {
            PanelClient.d(act);
        }
    }

    /** Reaplica em 1.5s, +3.5s e +7s (total ~12s) para pegar downloads grandes. */
    static final class DelayedCardsJob implements Runnable {
        private final Activity act;

        DelayedCardsJob(Activity act) {
            this.act = act;
        }

        @Override
        public void run() {
            long[] gaps = new long[] {1500L, 3500L, 7000L};
            for (int i = 0; i < gaps.length; i++) {
                try {
                    Thread.sleep(gaps[i]);
                } catch (InterruptedException ignored) {
                }
                try {
                    if (act.isFinishing()) {
                        return;
                    }
                    act.runOnUiThread(new ApplyCardsJob(act));
                } catch (Throwable ignored) {
                }
            }
        }
    }
}
