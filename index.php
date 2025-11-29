<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fix Us - Solusi Perbaikan untuk Anak Kos</title>
    <link rel="stylesheet" href="style.css">
    <style>
        .logo-container {
            width: 120px;
            height: 120px;
            margin: 0 auto 20px;
            animation: logoFloat 3s ease-in-out infinite;
        }
        
        .logo-svg {
            width: 100%;
            height: 100%;
            filter: drop-shadow(0 10px 20px rgba(102, 126, 234, 0.3));
        }
        
        @keyframes logoFloat {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }
    </style>
</head>
<body>
    <div class="landing-container">
        <div class="landing-card">
            <div class="logo-section">
                
                <!-- LOGO 5: MINIMALIST (AKTIF) -->
                <div class="logo-container">
                    <svg viewBox="0 0 200 200" xmlns="http://www.w3.org/2000/svg" class="logo-svg">
                        <!-- Background Circle -->
                        <circle cx="100" cy="100" r="95" fill="url(#gradient1)" />
                        
                        <!-- Gradients -->
                        <defs>
                            <linearGradient id="gradient1" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" style="stop-color:#667eea;stop-opacity:1" />
                                <stop offset="100%" style="stop-color:#764ba2;stop-opacity:1" />
                            </linearGradient>
                        </defs>
                        
                        <g transform="translate(100, 100)">
                            <!-- House/Home Shape -->
                            <path d="M -45,10 L 0,-35 L 45,10 L 45,50 L -45,50 Z" 
                                  fill="white" opacity="0.95" stroke="white" stroke-width="3"/>
                            
                            <!-- Roof Details -->
                            <path d="M -45,10 L 0,-35 L 45,10" 
                                  fill="none" stroke="#667eea" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
                            
                            <!-- Window Left -->
                            <rect x="-30" y="20" width="15" height="15" rx="2" fill="#667eea" opacity="0.6"/>
                            <line x1="-22.5" y1="20" x2="-22.5" y2="35" stroke="white" stroke-width="1.5"/>
                            <line x1="-30" y1="27.5" x2="-15" y2="27.5" stroke="white" stroke-width="1.5"/>
                            
                            <!-- Window Right -->
                            <rect x="15" y="20" width="15" height="15" rx="2" fill="#667eea" opacity="0.6"/>
                            <line x1="22.5" y1="20" x2="22.5" y2="35" stroke="white" stroke-width="1.5"/>
                            <line x1="15" y1="27.5" x2="30" y2="27.5" stroke="white" stroke-width="1.5"/>
                            
                            <!-- Door with Tools -->
                            <rect x="-8" y="25" width="16" height="25" rx="2" fill="#764ba2" opacity="0.8"/>
                            <circle cx="4" cy="37.5" r="1.5" fill="#ffd700"/>
                            
                            <!-- Wrench Icon on Door -->
                            <g transform="translate(0, 35)">
                                <path d="M -3,-8 L -3,-3 L 3,-3 L 3,-8 L 2,-9 L -2,-9 Z" 
                                      fill="#ffd700" stroke="#daa520" stroke-width="0.8"/>
                                <rect x="-1" y="-3" width="2" height="8" fill="#ffd700" stroke="#daa520" stroke-width="0.8"/>
                            </g>
                            
                            <!-- Big Tools Crossed in Front -->
                            <g opacity="0.9">
                                <!-- Wrench -->
                                <g transform="rotate(-30)">
                                    <rect x="-3" y="-55" width="6" height="50" rx="2" fill="white" stroke="#e0e0e0" stroke-width="2"/>
                                    <path d="M -8,-60 L -8,-55 L -3,-52 L 3,-52 L 8,-55 L 8,-60 Z" 
                                          fill="white" stroke="#e0e0e0" stroke-width="2"/>
                                    <rect x="-6" y="-58" width="12" height="5" fill="#667eea" opacity="0.3"/>
                                </g>
                                
                                <!-- Hammer -->
                                <g transform="rotate(30)">
                                    <rect x="-2.5" y="-45" width="5" height="45" rx="2" fill="white" stroke="#e0e0e0" stroke-width="2"/>
                                    <rect x="-15" y="-55" width="30" height="12" rx="2" fill="white" stroke="#e0e0e0" stroke-width="2"/>
                                </g>
                            </g>
                            
                            <!-- Center Circle Badge -->
                            <circle cx="0" cy="0" r="18" fill="#ffd700" stroke="#daa520" stroke-width="3"/>
                            <text x="0" y="7" font-family="Arial, sans-serif" font-size="20" font-weight="bold" 
                                  fill="#667eea" text-anchor="middle">FU</text>
                            
                            <!-- Sparkles -->
                            <g opacity="0.8">
                                <circle cx="-50" cy="-25" r="3" fill="white"/>
                                <circle cx="50" cy="-20" r="2.5" fill="white"/>
                                <circle cx="-40" cy="55" r="2" fill="white"/>
                                <circle cx="45" cy="55" r="2.5" fill="white"/>
                            </g>
                        </g>
                        
                        <!-- Border -->
                        <circle cx="100" cy="100" r="95" fill="none" stroke="white" stroke-width="3" opacity="0.3"/>
                    </svg>
                </div>
                
                <h1 class="logo">Fix Us</h1>
                <p class="tagline">Solusi Perbaikan untuk Anak Kos</p>
            </div>

            <div class="user-types">
                <div class="user-type-card konsumen-card">
                    <div class="icon">👤</div>
                    <h3>Konsumen</h3>
                    <p>Temukan tukang terpercaya</p>
                </div>
                
                <div class="user-type-card tukang-card">
                    <div class="icon">🔧</div>
                    <h3>Tukang</h3>
                    <p>Dapatkan lebih banyak pesanan</p>
                </div>
            </div>

            <div class="button-group">
                <a href="login.php" class="btn btn-primary">Masuk</a>
                <a href="register.php" class="btn btn-secondary">Daftar Sekarang</a>
            </div>
        </div>
    </div>
</body>
</html>