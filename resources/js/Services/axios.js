import axios from "axios";

const axiosInstance = axios.create({
    headers: {
        "X-Requested-With": "XMLHttpRequest",
        Accept: "application/json",
        "Content-Type": "application/json",
    },
    withCredentials: true,
});

// Intercepteur pour ajouter le token CSRF
axiosInstance.interceptors.request.use((config) => {
    // Récupérer le token CSRF depuis le meta tag
    const csrfToken = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute("content");

    if (csrfToken) {
        config.headers["X-CSRF-TOKEN"] = csrfToken;
    }

    return config;
});

export default axiosInstance;
