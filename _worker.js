export default {
  async fetch(request, env) {
    const url = new URL(request.url);
    const target = new URL(url.pathname + url.search, "https://ksrguruji.vercel.app");
    
    const reqHeaders = new Headers(request.headers);
    reqHeaders.set("X-Forwarded-Host", url.hostname);
    reqHeaders.set("X-Forwarded-Proto", url.protocol.replace(":", ""));
    
    const reqInit = {
      method: request.method,
      headers: reqHeaders,
      redirect: "follow"
    };
    
    if (request.method !== "GET" && request.method !== "HEAD") {
      reqInit.body = request.body;
    }
    
    try {
      const response = await fetch(target.toString(), reqInit);
      return response;
    } catch (err) {
      return new Response("KSR Guru Ji Proxy Error: " + err.message, { status: 502 });
    }
  }
};
