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
    const token = document.querySelector('meta[name="csrf-token"]');
    if (token) {
        config.headers["X-CSRF-TOKEN"] = token.getAttribute("content");
    }
    return config;
});

export default axiosInstance;
